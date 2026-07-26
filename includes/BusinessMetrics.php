<?php
/**
 * BusinessMetrics — single source of truth for executive revenue figures.
 *
 * Every admin surface (Dashboard, Bookings, Analytics) must read these helpers
 * so the numbers always agree:
 *
 *   Total Product Sales = paid shop orders + products sold inside completed bookings
 *   Total Labor Sales   = 100% of labor fees on completed bookings
 *   Total Revenue       = Total Product Sales + Total Labor Sales
 *   Shop Labor Earnings = Total Labor Sales × (1 - TECH_LABOR_SHARE)   (40%)
 *   Tech Labor Earnings = Total Labor Sales × TECH_LABOR_SHARE          (60%)
 *
 * Revenue dates: shop orders count on their created_at date; bookings count on
 * completed_at (falling back to created_at for legacy rows without it).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/TechnicianService.php'; // defines TECH_LABOR_SHARE

const SHOP_LABOR_SHARE = 1 - TECH_LABOR_SHARE;

/** WHERE fragment + params for an optional Y-m-d date window on a date column. */
function bizDateFilter(string $column, ?string $from, ?string $to): array {
    $sql = '';
    $params = [];
    if ($from !== null && $from !== '') {
        $sql .= " AND $column >= ?";
        $params[] = $from;
    }
    if ($to !== null && $to !== '') {
        $sql .= " AND $column <= ?";
        $params[] = $to;
    }
    return [$sql, $params];
}

/**
 * Core revenue totals, optionally scoped to a date window (Y-m-d, inclusive).
 *
 * @return array{
 *   shop_product_sales: float, booking_product_sales: float, product_sales: float,
 *   labor_sales: float, shop_labor: float, tech_labor: float, total_revenue: float
 * }
 */
function bizTotals(?string $from = null, ?string $to = null): array {
    [$orderDate, $orderParams] = bizDateFilter('DATE(o.created_at)', $from, $to);
    [$bookDate, $bookParams] = bizDateFilter('DATE(COALESCE(b.completed_at, b.created_at))', $from, $to);

    $shopProducts = (float)(fetchOne(
        "SELECT COALESCE(SUM(o.total),0) AS n FROM orders o WHERE o.payment_status = 'paid'$orderDate",
        $orderParams
    )['n'] ?? 0);

    $bookingProducts = (float)(fetchOne(
        "SELECT COALESCE(SUM(bp.product_price),0) AS n
         FROM booking_products bp
         JOIN bookings b ON b.id = bp.booking_id
         WHERE b.status = 'completed'$bookDate",
        $bookParams
    )['n'] ?? 0);

    $laborSales = (float)(fetchOne(
        "SELECT COALESCE(SUM(bs.labor_fee),0) AS n
         FROM booking_services bs
         JOIN bookings b ON b.id = bs.booking_id
         WHERE b.status = 'completed'$bookDate",
        $bookParams
    )['n'] ?? 0);

    $productSales = $shopProducts + $bookingProducts;

    return [
        'shop_product_sales'    => $shopProducts,
        'booking_product_sales' => $bookingProducts,
        'product_sales'         => $productSales,
        'labor_sales'           => $laborSales,
        'shop_labor'            => $laborSales * SHOP_LABOR_SHARE,
        'tech_labor'            => $laborSales * TECH_LABOR_SHARE,
        'total_revenue'         => $productSales + $laborSales,
    ];
}

/**
 * Revenue time series with product/labor split.
 *
 * @param string $granularity daily|weekly|monthly|yearly
 * @return array<int, array{bucket: string, product_sales: float, labor_sales: float}>
 */
function bizRevenueSeries(string $granularity = 'daily', ?string $from = null, ?string $to = null): array {
    $bucketExpr = match ($granularity) {
        'weekly'  => "DATE_FORMAT(d, '%x-W%v')",
        'monthly' => "DATE_FORMAT(d, '%Y-%m')",
        'yearly'  => "DATE_FORMAT(d, '%Y')",
        default   => "DATE_FORMAT(d, '%Y-%m-%d')",
    };

    [$dateSql, $dateParams] = bizDateFilter('d', $from, $to);
    $where = $dateSql !== '' ? 'WHERE ' . substr($dateSql, 5) : '';

    return array_map(
        fn($r) => [
            'bucket'        => $r['bucket'],
            'product_sales' => (float)$r['product_sales'],
            'labor_sales'   => (float)$r['labor_sales'],
        ],
        fetchAllRows(
            "SELECT $bucketExpr AS bucket,
                    COALESCE(SUM(product_sales),0) AS product_sales,
                    COALESCE(SUM(labor_sales),0) AS labor_sales
             FROM (
                SELECT DATE(o.created_at) AS d, o.total AS product_sales, 0 AS labor_sales
                FROM orders o
                WHERE o.payment_status = 'paid'
                UNION ALL
                SELECT DATE(COALESCE(b.completed_at, b.created_at)) AS d,
                       COALESCE((SELECT SUM(bp.product_price) FROM booking_products bp WHERE bp.booking_id = b.id), 0),
                       COALESCE((SELECT SUM(bs.labor_fee) FROM booking_services bs WHERE bs.booking_id = b.id), 0)
                FROM bookings b
                WHERE b.status = 'completed'
             ) rev
             $where
             GROUP BY bucket
             ORDER BY MIN(d) ASC",
            $dateParams
        )
    );
}

/**
 * Top-selling products across BOTH sales channels (shop orders + booking products).
 *
 * @return array<int, array{product_name: string, units: int, revenue: float}>
 */
function bizTopProducts(int $limit = 8, ?string $from = null, ?string $to = null): array {
    [$dateSql, $dateParams] = bizDateFilter('d', $from, $to);
    $where = $dateSql !== '' ? 'WHERE ' . substr($dateSql, 5) : '';

    return array_map(
        fn($r) => [
            'product_name' => $r['product_name'],
            'units'        => (int)$r['units'],
            'revenue'      => (float)$r['revenue'],
        ],
        fetchAllRows(
            "SELECT product_name, SUM(units) AS units, SUM(revenue) AS revenue
             FROM (
                SELECT COALESCE(p.name, CONCAT('Product #', oi.product_id)) AS product_name,
                       oi.quantity AS units, oi.quantity * oi.price AS revenue,
                       DATE(o.created_at) AS d
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                LEFT JOIN products p ON p.id = oi.product_id
                WHERE o.payment_status = 'paid'
                UNION ALL
                SELECT COALESCE(p2.name, bp.product_name, CONCAT('Product #', bp.product_id)),
                       1, bp.product_price,
                       DATE(COALESCE(b.completed_at, b.created_at))
                FROM booking_products bp
                JOIN bookings b ON b.id = bp.booking_id
                LEFT JOIN products p2 ON p2.id = bp.product_id
                WHERE b.status = 'completed'
             ) sales
             $where
             GROUP BY product_name
             ORDER BY units DESC, revenue DESC
             LIMIT " . (int)$limit,
            $dateParams
        )
    );
}

/**
 * Most requested services on completed bookings, with labor revenue.
 *
 * @return array<int, array{service_name: string, requests: int, labor: float}>
 */
function bizTopServices(int $limit = 8, ?string $from = null, ?string $to = null): array {
    [$dateSql, $dateParams] = bizDateFilter('DATE(COALESCE(b.completed_at, b.created_at))', $from, $to);

    return array_map(
        fn($r) => [
            'service_name' => $r['service_name'],
            'requests'     => (int)$r['requests'],
            'labor'        => (float)$r['labor'],
        ],
        fetchAllRows(
            "SELECT COALESCE(bs.service_name, CONCAT('Service #', bs.service_id)) AS service_name,
                    COUNT(*) AS requests,
                    COALESCE(SUM(bs.labor_fee),0) AS labor
             FROM booking_services bs
             JOIN bookings b ON b.id = bs.booking_id
             WHERE b.status = 'completed'$dateSql
             GROUP BY bs.service_id, service_name
             ORDER BY requests DESC, labor DESC
             LIMIT " . (int)$limit,
            $dateParams
        )
    );
}

/**
 * Technician leaderboard from completed bookings: jobs, customers, labor and the 60% share.
 *
 * @return array<int, array{tech_name: string, jobs_done: int, customers: int, labor: float, tech_share: float}>
 */
function bizTechPerformance(int $limit = 8, ?string $from = null, ?string $to = null): array {
    [$dateSql, $dateParams] = bizDateFilter('DATE(COALESCE(b.completed_at, b.created_at))', $from, $to);

    return array_map(
        fn($r) => [
            'tech_name'  => $r['tech_name'],
            'jobs_done'  => (int)$r['jobs_done'],
            'customers'  => (int)$r['customers'],
            'labor'      => (float)$r['labor'],
            'tech_share' => (float)$r['labor'] * TECH_LABOR_SHARE,
        ],
        fetchAllRows(
            "SELECT u.name AS tech_name,
                    COUNT(DISTINCT b.id) AS jobs_done,
                    COUNT(DISTINCT b.user_id) AS customers,
                    COALESCE(SUM(bs.labor_fee),0) AS labor
             FROM bookings b
             JOIN users u ON u.id = b.technician_id
             LEFT JOIN booking_services bs ON bs.booking_id = b.id
             WHERE b.status = 'completed'$dateSql
             GROUP BY b.technician_id, u.name
             ORDER BY jobs_done DESC, labor DESC
             LIMIT " . (int)$limit,
            $dateParams
        )
    );
}

/** Human label for a bookings payment state (bookings carry no payment record; settled in-shop on completion). */
function bizBookingPaymentState(string $status): array {
    return match ($status) {
        'completed' => ['label' => 'Paid', 'color' => '#15803d'],
        'cancelled' => ['label' => '—', 'color' => '#6b7280'],
        default     => ['label' => 'Awaiting', 'color' => '#d97706'],
    };
}
