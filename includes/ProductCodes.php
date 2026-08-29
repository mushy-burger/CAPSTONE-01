<?php
/**
 * Product identification codes — barcode / QR support for MotoTrack.
 *
 * Codes live in `product_codes`, one row per code, so a product can hold a
 * manufacturer barcode and a MotoTrack-generated code at the same time.
 * `products`.`id` stays the product identity; codes are only lookup keys.
 *
 * Symbols are rendered as inline SVG. The GD extension is not enabled in this
 * environment, so raster generation is not an option and SVG also prints at
 * the printer's own resolution, which keeps scans reliable.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

const MTX_CODE_PREFIX = 'MT-P-';

/**
 * Build the MotoTrack code for a product.
 *
 * Derived from the AUTO_INCREMENT primary key, which is already unique and
 * never changes, so the code inherits that uniqueness and stays stable for the
 * life of the product. No randomness, so no chance of collision.
 */
function mtxProductCode(int $productId): string {
    return MTX_CODE_PREFIX . str_pad((string)$productId, 6, '0', STR_PAD_LEFT);
}

/** True when a string looks like a MotoTrack-generated code. */
function mtxIsGeneratedCode(string $code): bool {
    return preg_match('/^' . preg_quote(MTX_CODE_PREFIX, '/') . '\d{6}$/', strtoupper(trim($code))) === 1;
}

/**
 * Normalise a scanned or typed code.
 *
 * Scanners often append a newline and may pad with spaces. MotoTrack codes are
 * upper-cased so "mt-p-000123" and "MT-P-000123" resolve to the same product;
 * manufacturer codes keep their original case since some are case-sensitive.
 */
function mtxNormalizeCode(string $code): string {
    $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $code) ?? '');
    return mtxIsGeneratedCode($code) ? strtoupper($code) : $code;
}

/** Every code attached to a product, generated codes first. */
function mtxGetProductCodes(int $productId): array {
    return fetchAllRows(
        "SELECT id, product_id, code, code_type, symbology, created_at
         FROM product_codes
         WHERE product_id = ?
         ORDER BY (code_type = 'mototrack') DESC, id",
        [$productId]
    );
}

/** The product's MotoTrack-generated code row, if it has one. */
function mtxGetGeneratedCode(int $productId): ?array {
    return fetchOne(
        "SELECT id, product_id, code, code_type, symbology
         FROM product_codes
         WHERE product_id = ? AND code_type = 'mototrack'
         LIMIT 1",
        [$productId]
    );
}

/** Codes for many products at once, keyed by product id (avoids N+1 in lists). */
function mtxGetCodesForProducts(array $productIds): array {
    $ids = array_values(array_unique(array_map('intval', $productIds)));
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = fetchAllRows(
        "SELECT id, product_id, code, code_type, symbology
         FROM product_codes
         WHERE product_id IN ($placeholders)
         ORDER BY (code_type = 'mototrack') DESC, id",
        $ids
    );

    $byProduct = [];
    foreach ($rows as $row) {
        $byProduct[(int)$row['product_id']][] = $row;
    }
    return $byProduct;
}

/**
 * Which product owns this code, if any.
 *
 * This is the lookup a scanner drives: code -> product row.
 */
function mtxFindProductByCode(string $code): ?array {
    $code = mtxNormalizeCode($code);
    if ($code === '') {
        return null;
    }

    return fetchOne(
        "SELECT p.*, c.name AS category_name, pc.code, pc.code_type
         FROM product_codes pc
         INNER JOIN products p ON p.id = pc.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE pc.code = ?
         LIMIT 1",
        [$code]
    );
}

/**
 * The product currently holding this code, ignoring $ignoreProductId.
 *
 * Used to report a friendly conflict before hitting the UNIQUE constraint.
 */
function mtxCodeOwner(string $code, int $ignoreProductId = 0): ?array {
    $code = mtxNormalizeCode($code);
    if ($code === '') {
        return null;
    }

    return fetchOne(
        "SELECT pc.code, pc.product_id, p.name AS product_name
         FROM product_codes pc
         INNER JOIN products p ON p.id = pc.product_id
         WHERE pc.code = ? AND pc.product_id != ?
         LIMIT 1",
        [$code, $ignoreProductId]
    );
}

/**
 * Validate a code before saving.
 *
 * Returns an error string, or '' when the code is acceptable.
 */
function mtxValidateCode(string $code, int $productId = 0): string {
    $code = mtxNormalizeCode($code);

    if ($code === '') {
        return 'Enter or scan a code.';
    }
    if (mb_strlen($code) > 64) {
        return 'That code is too long (maximum 64 characters).';
    }
    // Code 128 covers printable ASCII; anything outside it cannot be printed
    // as a scannable barcode.
    if (preg_match('/^[\x20-\x7E]+$/', $code) !== 1) {
        return 'That code contains characters a barcode scanner cannot read.';
    }
    // Ownership is checked first: scanning a label that belongs to another
    // product is the common mistake, and naming that product is the most
    // useful thing to say.
    $owner = mtxCodeOwner($code, $productId);
    if ($owner) {
        return 'Code ' . $code . ' is already assigned to "' . $owner['product_name'] . '".';
    }

    if (mtxIsGeneratedCode($code) && $code !== mtxProductCode($productId)) {
        return 'MT-P- codes are generated by MotoTrack and cannot be typed in by hand.';
    }

    return '';
}

/**
 * Attach a code to a product.
 *
 * Re-saving the same code for the same product updates its symbology instead of
 * failing, so editing a product repeatedly is harmless.
 */
function mtxSaveCode(int $productId, string $code, string $codeType, string $symbology): void {
    $code = mtxNormalizeCode($code);
    if ($productId <= 0 || $code === '') {
        return;
    }

    $codeType  = in_array($codeType, ['manufacturer', 'mototrack'], true) ? $codeType : 'manufacturer';
    $symbology = in_array($symbology, ['qr', 'barcode', 'both'], true) ? $symbology : 'both';

    getDB()->prepare(
        "INSERT INTO product_codes (product_id, code, code_type, symbology)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            product_id = IF(product_id = VALUES(product_id), product_id, product_id),
            symbology  = VALUES(symbology)"
    )->execute([$productId, $code, $codeType, $symbology]);
}

/** Generate and store the MotoTrack code for a product, returning it. */
function mtxAssignGeneratedCode(int $productId, string $symbology = 'both'): string {
    $code = mtxProductCode($productId);
    mtxSaveCode($productId, $code, 'mototrack', $symbology);
    return $code;
}

/** Remove one code from a product. */
function mtxDeleteCode(int $productId, int $codeId): void {
    getDB()->prepare("DELETE FROM product_codes WHERE id = ? AND product_id = ?")
        ->execute([$codeId, $productId]);
}

// ---------------------------------------------------------------------------
// Code 128 barcode rendering (SVG)
// ---------------------------------------------------------------------------

/**
 * Bar/space width patterns for Code 128, indexed by symbol value 0-106.
 * Each string is six digits: alternating bar and space widths in modules.
 */
function mtxCode128Patterns(): array {
    static $patterns = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112',
    ];
    return $patterns;
}

/**
 * Encode a string into Code 128 symbol values using code set B.
 *
 * Code set B covers all printable ASCII, which is what MotoTrack codes and
 * typical manufacturer codes use. Returns an empty array if the value cannot
 * be encoded.
 */
function mtxCode128Values(string $value): array {
    if ($value === '' || preg_match('/^[\x20-\x7E]+$/', $value) !== 1) {
        return [];
    }

    $startB = 104;
    $values = [$startB];
    $checksum = $startB;

    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        // In code set B, symbol value = ASCII - 32.
        $symbol = ord($value[$i]) - 32;
        $values[] = $symbol;
        $checksum += $symbol * ($i + 1);
    }

    $values[] = $checksum % 103; // check digit
    $values[] = 106;             // stop pattern
    return $values;
}

/**
 * Render a Code 128 barcode as an SVG string.
 *
 * Returns '' when the value cannot be encoded, so callers can fall back to
 * showing the code as text.
 */
function mtxBarcodeSvg(string $value, int $height = 60, float $moduleWidth = 1.6, bool $showText = true): string {
    $values = mtxCode128Values($value);
    if (!$values) {
        return '';
    }

    $patterns = mtxCode128Patterns();
    $quietZone = 10; // modules of blank space required either side of the symbol

    $bars = [];
    $x = $quietZone;
    foreach ($values as $symbolValue) {
        $pattern = $patterns[$symbolValue] ?? '';
        $isBar = true;
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $width = (int)$pattern[$i];
            if ($isBar) {
                $bars[] = ['x' => $x, 'w' => $width];
            }
            $x += $width;
            $isBar = !$isBar;
        }
    }

    $totalModules = $x + $quietZone;
    $textHeight = $showText ? 18 : 0;
    $svgWidth = $totalModules * $moduleWidth;
    $svgHeight = $height + $textHeight;

    $rects = '';
    foreach ($bars as $bar) {
        $rects .= sprintf(
            '<rect x="%.3f" y="0" width="%.3f" height="%d"/>',
            $bar['x'] * $moduleWidth,
            $bar['w'] * $moduleWidth,
            $height
        );
    }

    $text = '';
    if ($showText) {
        $text = sprintf(
            '<text x="%.3f" y="%d" text-anchor="middle" font-family="monospace" font-size="13" letter-spacing="1.5" fill="#17191f">%s</text>',
            $svgWidth / 2,
            $height + 14,
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
        );
    }

    return sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.3f %d" width="%.3f" height="%d" role="img" aria-label="Barcode %s">'
        . '<rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g>%s</svg>',
        $svgWidth,
        $svgHeight,
        $svgWidth,
        $svgHeight,
        htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
        $rects,
        $text
    );
}

// ---------------------------------------------------------------------------
// QR code rendering (SVG)
// ---------------------------------------------------------------------------

/**
 * Build a QR code matrix for short ASCII payloads.
 *
 * Implements QR model 2, byte mode, error correction level M, versions 1-4
 * (up to 62 bytes) — comfortably more than the 11 characters of an MT-P- code
 * and enough for typical manufacturer codes. Returns a square array of
 * booleans, or null when the payload does not fit.
 */
function mtxQrMatrix(string $data): ?array {
    $length = strlen($data);

    // [version => byte capacity at EC level M]
    $capacities = [1 => 14, 2 => 26, 3 => 42, 4 => 62];
    // [version => [total codewords, EC codewords per block, block count]]
    $specs = [
        1 => [26, 10, 1],
        2 => [44, 16, 1],
        3 => [70, 26, 1],
        4 => [100, 18, 2],
    ];

    $version = 0;
    foreach ($capacities as $candidate => $capacity) {
        if ($length <= $capacity) {
            $version = $candidate;
            break;
        }
    }
    if ($version === 0) {
        return null;
    }

    [$totalCodewords, $ecPerBlock, $blockCount] = $specs[$version];
    $dataCodewords = $totalCodewords - ($ecPerBlock * $blockCount);

    // --- Build the bit stream: mode indicator, length, payload, terminator ---
    $bits = '0100';                                    // byte mode
    $bits .= str_pad(decbin($length), 8, '0', STR_PAD_LEFT);
    for ($i = 0; $i < $length; $i++) {
        $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }

    $capacityBits = $dataCodewords * 8;
    $bits .= str_repeat('0', min(4, max(0, $capacityBits - strlen($bits))));
    if (strlen($bits) % 8 !== 0) {
        $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
    }

    $codewords = [];
    foreach (str_split($bits, 8) as $byte) {
        $codewords[] = bindec($byte);
    }
    // Pad alternately with 236 / 17 until the data capacity is filled.
    $pad = [236, 17];
    $padIndex = 0;
    while (count($codewords) < $dataCodewords) {
        $codewords[] = $pad[$padIndex % 2];
        $padIndex++;
    }

    // --- Split into blocks and append Reed-Solomon error correction ---
    $blocks = [];
    $perBlock = intdiv($dataCodewords, $blockCount);
    $remainder = $dataCodewords % $blockCount;
    $offset = 0;
    for ($b = 0; $b < $blockCount; $b++) {
        $size = $perBlock + ($b >= $blockCount - $remainder ? 1 : 0);
        $blocks[] = array_slice($codewords, $offset, $size);
        $offset += $size;
    }

    $ecBlocks = [];
    foreach ($blocks as $block) {
        $ecBlocks[] = mtxQrErrorCorrection($block, $ecPerBlock);
    }

    // Interleave data codewords, then error correction codewords.
    $final = [];
    $maxData = max(array_map('count', $blocks));
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($blocks as $block) {
            if (isset($block[$i])) {
                $final[] = $block[$i];
            }
        }
    }
    for ($i = 0; $i < $ecPerBlock; $i++) {
        foreach ($ecBlocks as $ecBlock) {
            if (isset($ecBlock[$i])) {
                $final[] = $ecBlock[$i];
            }
        }
    }

    return mtxQrBuildMatrix($final, $version);
}

/** Galois field log/antilog tables for GF(256), used by Reed-Solomon. */
function mtxQrGaloisTables(): array {
    static $tables = null;
    if ($tables === null) {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D; // QR's generator polynomial
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        $tables = [$exp, $log];
    }
    return $tables;
}

/** Reed-Solomon error correction codewords for one block. */
function mtxQrErrorCorrection(array $block, int $ecCount): array {
    [$exp, $log] = mtxQrGaloisTables();

    // Build the generator polynomial for $ecCount codewords.
    $generator = [1];
    for ($i = 0; $i < $ecCount; $i++) {
        $next = array_fill(0, count($generator) + 1, 0);
        foreach ($generator as $index => $coeff) {
            $next[$index] ^= $coeff;
            if ($coeff !== 0) {
                $next[$index + 1] ^= $exp[($log[$coeff] + $i) % 255];
            }
        }
        $generator = $next;
    }

    $remainder = array_merge($block, array_fill(0, $ecCount, 0));
    $blockLength = count($block);
    for ($i = 0; $i < $blockLength; $i++) {
        $lead = $remainder[$i];
        if ($lead === 0) {
            continue;
        }
        $leadLog = $log[$lead];
        foreach ($generator as $index => $coeff) {
            if ($coeff !== 0) {
                $remainder[$i + $index] ^= $exp[($log[$coeff] + $leadLog) % 255];
            }
        }
    }

    return array_slice($remainder, $blockLength, $ecCount);
}

/**
 * Place codewords, function patterns and format info into the QR matrix.
 * Uses mask pattern 0, which is valid for any payload.
 */
function mtxQrBuildMatrix(array $codewords, int $version): array {
    $size = 17 + ($version * 4);
    $matrix = array_fill(0, $size, array_fill(0, $size, null));
    $reserved = array_fill(0, $size, array_fill(0, $size, false));

    $setFunction = static function (int $row, int $col, bool $value) use (&$matrix, &$reserved, $size): void {
        if ($row < 0 || $col < 0 || $row >= $size || $col >= $size) {
            return;
        }
        $matrix[$row][$col] = $value;
        $reserved[$row][$col] = true;
    };

    // Finder patterns with their separators, at three corners.
    foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$originRow, $originCol]) {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $inRing = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                    || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6));
                $inCore = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;
                $setFunction($originRow + $r, $originCol + $c, $inRing || $inCore);
            }
        }
    }

    // Timing patterns.
    for ($i = 8; $i < $size - 8; $i++) {
        $setFunction(6, $i, $i % 2 === 0);
        $setFunction($i, 6, $i % 2 === 0);
    }

    // Alignment pattern (versions 2-4 have exactly one).
    if ($version >= 2) {
        $center = $size - 7;
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $setFunction($center + $r, $center + $c, max(abs($r), abs($c)) !== 1);
            }
        }
    }

    // Dark module, always set.
    $setFunction($size - 8, 8, true);

    // Reserve the format information areas before laying out data.
    for ($i = 0; $i < 9; $i++) {
        if ($matrix[8][$i] === null) { $setFunction(8, $i, false); }
        if ($matrix[$i][8] === null) { $setFunction($i, 8, false); }
    }
    for ($i = $size - 8; $i < $size; $i++) {
        if ($matrix[8][$i] === null) { $setFunction(8, $i, false); }
        if ($matrix[$i][8] === null) { $setFunction($i, 8, false); }
    }

    // Lay the data bits out in the standard zig-zag, applying mask 0.
    $bitString = '';
    foreach ($codewords as $codeword) {
        $bitString .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
    }

    $bitIndex = 0;
    $totalBits = strlen($bitString);
    $upward = true;

    for ($col = $size - 1; $col > 0; $col -= 2) {
        if ($col === 6) {
            $col--; // skip the vertical timing column
        }
        for ($i = 0; $i < $size; $i++) {
            $row = $upward ? ($size - 1 - $i) : $i;
            for ($offset = 0; $offset < 2; $offset++) {
                $currentCol = $col - $offset;
                if ($reserved[$row][$currentCol]) {
                    continue;
                }
                $bit = $bitIndex < $totalBits ? $bitString[$bitIndex] === '1' : false;
                $bitIndex++;
                // Mask 0: invert where (row + col) is even.
                if ((($row + $currentCol) % 2) === 0) {
                    $bit = !$bit;
                }
                $matrix[$row][$currentCol] = $bit;
            }
        }
        $upward = !$upward;
    }

    // Format information for EC level M with mask 0, as specified by the standard.
    $formatBits = '101010000010010';
    for ($i = 0; $i <= 5; $i++) {
        $matrix[8][$i] = $formatBits[$i] === '1';
        $matrix[$i][8] = $formatBits[14 - $i] === '1';
    }
    $matrix[8][7] = $formatBits[6] === '1';
    $matrix[8][8] = $formatBits[7] === '1';
    $matrix[7][8] = $formatBits[8] === '1';
    for ($i = 9; $i <= 14; $i++) {
        $matrix[$size - 15 + $i][8] = $formatBits[$i] === '1';
    }
    for ($i = 0; $i <= 7; $i++) {
        $matrix[8][$size - 1 - $i] = $formatBits[$i] === '1';
    }

    // Any cell still unset is light.
    foreach ($matrix as $row => $cells) {
        foreach ($cells as $col => $cell) {
            if ($cell === null) {
                $matrix[$row][$col] = false;
            }
        }
    }

    return $matrix;
}

/**
 * Render a QR code as an SVG string.
 *
 * Returns '' when the payload cannot be encoded, so callers can fall back to
 * showing the code as text.
 */
function mtxQrSvg(string $data, int $pixelSize = 140): string {
    $matrix = mtxQrMatrix($data);
    if ($matrix === null) {
        return '';
    }

    $size = count($matrix);
    $quietZone = 4; // required blank border, in modules
    $total = $size + ($quietZone * 2);

    $rects = '';
    foreach ($matrix as $row => $cells) {
        foreach ($cells as $col => $isDark) {
            if ($isDark) {
                $rects .= sprintf('<rect x="%d" y="%d" width="1" height="1"/>', $col + $quietZone, $row + $quietZone);
            }
        }
    }

    return sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" shape-rendering="crispEdges" role="img" aria-label="QR code %s">'
        . '<rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g></svg>',
        $total,
        $total,
        $pixelSize,
        $pixelSize,
        htmlspecialchars($data, ENT_QUOTES, 'UTF-8'),
        $rects
    );
}
