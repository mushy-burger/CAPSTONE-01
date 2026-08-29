<?php
/**
 * Printable product label.
 *
 * GET ?id=<product_id>[&code=<code>][&copies=N]
 * Renders label(s) with the product name, its identification code, and the
 * barcode / QR symbols, then opens the browser print dialog.
 *
 * Standalone page (no admin sidebar) so the print output contains only the
 * labels themselves.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ProductCodes.php';
requireAdminOrStaff();

$productId = (int)($_GET['id'] ?? 0);
$product = $productId ? fetchOne("SELECT * FROM products WHERE id = ?", [$productId]) : null;

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Label not found';
}

$copies = max(1, min(24, (int)($_GET['copies'] ?? 1)));

// Which code to print: the one requested, else the generated code, else the
// first code on file.
$codes = $product ? mtxGetProductCodes($productId) : [];
$requested = mtxNormalizeCode((string)($_GET['code'] ?? ''));
$selected = null;
foreach ($codes as $row) {
    if ($requested !== '' && $row['code'] === $requested) {
        $selected = $row;
        break;
    }
}
if ($selected === null) {
    $selected = $codes[0] ?? null;
}

$code = $selected['code'] ?? '';
$symbology = $selected['symbology'] ?? 'both';
$showQr = in_array($symbology, ['qr', 'both'], true);
$showBarcode = in_array($symbology, ['barcode', 'both'], true);

$qrSvg = ($code !== '' && $showQr) ? mtxQrSvg($code, 132) : '';
$barcodeSvg = ($code !== '' && $showBarcode) ? mtxBarcodeSvg($code, 54, 1.5) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $product ? htmlspecialchars($product['name']) . ' — Label' : 'Label not found' ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root { --ink:#17191f; --muted:#6b7280; --line:#e5e7eb; --accent:#d71920; --soft:#f4f6f8; }
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 28px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: var(--ink); background: var(--soft);
    }
    .lbl-bar {
      max-width: 900px; margin: 0 auto 22px; display: flex; flex-wrap: wrap;
      gap: 12px; align-items: center; justify-content: space-between;
    }
    .lbl-bar h1 { font-size: 1.25rem; margin: 0 0 4px; }
    .lbl-bar p { margin: 0; color: var(--muted); font-size: .85rem; }
    .lbl-actions { display: flex; gap: 10px; align-items: center; }
    .lbl-btn {
      display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 18px;
      border-radius: 12px; border: 1px solid var(--line); background: #fff; color: var(--ink);
      font: inherit; font-weight: 800; font-size: .85rem; cursor: pointer; text-decoration: none;
    }
    .lbl-btn--primary { background: var(--accent); border-color: var(--accent); color: #fff; }
    .lbl-btn select { font: inherit; border: 0; background: transparent; font-weight: 800; }

    .lbl-sheet {
      max-width: 900px; margin: 0 auto; display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px;
    }
    .lbl {
      background: #fff; border: 1px solid var(--line); border-radius: 12px;
      padding: 14px; text-align: center; page-break-inside: avoid; break-inside: avoid;
    }
    .lbl-brand {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      font-size: .62rem; font-weight: 900; letter-spacing: .14em; text-transform: uppercase;
      color: var(--accent); margin-bottom: 6px;
    }
    .lbl-name {
      font-size: .84rem; font-weight: 800; line-height: 1.3; margin: 0 0 2px;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .lbl-meta { font-size: .68rem; color: var(--muted); margin: 0 0 10px; }
    .lbl-symbols { display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .lbl-symbols svg { display: block; max-width: 100%; height: auto; }
    .lbl-code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: .78rem; font-weight: 700; letter-spacing: .06em; margin-top: 8px;
    }
    .lbl-price { font-size: .9rem; font-weight: 900; margin-top: 6px; }
    .lbl-empty {
      max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid var(--line);
      border-radius: 14px; padding: 40px; text-align: center; color: var(--muted);
    }
    .lbl-empty i { font-size: 2rem; color: var(--accent); margin-bottom: 10px; display: block; }

    @media print {
      body { background: #fff; padding: 0; }
      .lbl-bar { display: none !important; }
      .lbl-sheet { max-width: none; gap: 0; grid-template-columns: repeat(3, 1fr); }
      .lbl { border: 1px dashed #999; border-radius: 0; margin: 0; }
      @page { margin: 10mm; }
    }
  </style>
</head>
<body>

<div class="lbl-bar">
  <div>
    <h1><?= $product ? htmlspecialchars($product['name']) : 'Label not found' ?></h1>
    <p>
      <?php if ($code !== ''): ?>
        Identification code <strong><?= htmlspecialchars($code) ?></strong> · attach to the physical product.
      <?php else: ?>
        This product has no identification code yet.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($code !== ''): ?>
    <div class="lbl-actions">
      <label class="lbl-btn">
        <i class="fas fa-copy"></i>
        <select id="lblCopies">
          <?php foreach ([1, 2, 4, 6, 8, 12, 24] as $option): ?>
            <option value="<?= $option ?>" <?= $copies === $option ? 'selected' : '' ?>><?= $option ?> label<?= $option === 1 ? '' : 's' ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="button" class="lbl-btn lbl-btn--primary" id="lblPrint">
        <i class="fas fa-print"></i> Print
      </button>
    </div>
  <?php endif; ?>
</div>

<?php if (!$product): ?>
  <div class="lbl-empty">
    <i class="fas fa-triangle-exclamation"></i>
    <strong>That product no longer exists.</strong>
  </div>
<?php elseif ($code === ''): ?>
  <div class="lbl-empty">
    <i class="fas fa-barcode"></i>
    <strong>No identification code assigned.</strong>
    <p>Open this product in Products and add or generate a code first.</p>
  </div>
<?php else: ?>
  <div class="lbl-sheet">
    <?php for ($i = 0; $i < $copies; $i++): ?>
      <div class="lbl">
        <div class="lbl-brand"><i class="fas fa-motorcycle"></i> MotoTrack</div>
        <p class="lbl-name"><?= htmlspecialchars($product['name']) ?></p>
        <p class="lbl-meta">
          <?= htmlspecialchars($product['brand'] ?: 'Product') ?>
        </p>
        <div class="lbl-symbols">
          <?php if ($qrSvg !== ''): ?><?= $qrSvg ?><?php endif; ?>
          <?php if ($barcodeSvg !== ''): ?><?= $barcodeSvg ?><?php endif; ?>
        </div>
        <?php if ($barcodeSvg === ''): ?>
          <div class="lbl-code"><?= htmlspecialchars($code) ?></div>
        <?php endif; ?>
        <div class="lbl-price"><?= formatPrice((float)$product['price']) ?></div>
      </div>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<script>
(function () {
  var printBtn = document.getElementById('lblPrint');
  var copies = document.getElementById('lblCopies');
  if (printBtn) {
    printBtn.addEventListener('click', function () { window.print(); });
  }
  // Changing the copy count reloads with the new value, keeping ?ctx intact.
  if (copies) {
    copies.addEventListener('change', function () {
      var url = new URL(window.location.href);
      url.searchParams.set('copies', copies.value);
      window.location.href = url.toString();
    });
  }
  // Opened with ?autoprint=1 straight from the product form.
  if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
  }
})();
</script>
<?= authContextScriptTag() ?>
</body>
</html>
