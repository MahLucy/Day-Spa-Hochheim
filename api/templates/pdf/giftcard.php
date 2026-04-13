<?php
/**
 * Template HTML para geração de PDF via Dompdf (A5 portrait).
 *
 * @var string               $code
 * @var array                $order
 * @var array                $items           Order items (com service_snapshot)
 * @var array                $giftCardItems   Gift card items (snapshot imutável)
 * @var \DateTimeImmutable   $validUntil
 * @var string               $qrCodeBase64    data:image/png;base64,...
 */
$validUntilFmt  = $validUntil->format('d/m/Y');
$totalFormatted = 'R$ ' . number_format((float) $order['total'], 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  @page { margin: 0; size: A5 portrait; }

  body {
    font-family: Helvetica, Arial, sans-serif;
    background: #fff;
    color: #3d2c2c;
    width: 148mm;
    min-height: 210mm;
  }

  .card {
    width: 148mm;
    min-height: 210mm;
    background: #fff;
    position: relative;
    overflow: hidden;
  }

  /* Faixa superior */
  .top-band {
    background: #3d2c2c;
    padding: 18mm 12mm 14mm;
    text-align: center;
    position: relative;
  }

  .brand {
    font-size: 9pt;
    letter-spacing: 4pt;
    text-transform: uppercase;
    color: #c9a96e;
    margin-bottom: 2mm;
  }

  .brand-sub {
    font-size: 7pt;
    letter-spacing: 2pt;
    color: #ffffff50;
  }

  /* Ornamento decorativo */
  .ornament {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3mm;
    margin: 4mm 0;
  }
  .ornament-line { width: 20mm; height: 0.5pt; background: #c9a96e; }
  .ornament-dot  { width: 3pt; height: 3pt; background: #c9a96e; transform: rotate(45deg); }

  /* Código do cartão */
  .code-section {
    background: linear-gradient(135deg, #5a3e3e, #3d2c2c);
    margin: 8mm 10mm;
    border-radius: 4mm;
    padding: 6mm 10mm;
    text-align: center;
    border: 0.5pt solid #c9a96e40;
  }

  .code-label {
    font-size: 6pt;
    letter-spacing: 2pt;
    text-transform: uppercase;
    color: #c9a96e80;
    margin-bottom: 2mm;
  }

  .code {
    font-size: 18pt;
    font-weight: bold;
    color: #c9a96e;
    letter-spacing: 4pt;
    font-family: Courier, monospace;
  }

  .validity {
    font-size: 7pt;
    color: #ffffff60;
    margin-top: 2mm;
  }

  /* Corpo */
  .body-section {
    padding: 4mm 12mm 6mm;
  }

  .section-title {
    font-size: 7pt;
    letter-spacing: 2pt;
    text-transform: uppercase;
    color: #c9a96e;
    border-bottom: 0.5pt solid #e8ddd0;
    padding-bottom: 1.5mm;
    margin-bottom: 3mm;
  }

  /* Serviços */
  .service-item {
    padding: 2.5mm 0;
    border-bottom: 0.5pt solid #f0e8e0;
  }

  .service-item:last-child { border-bottom: none; }

  .service-name {
    font-size: 9pt;
    font-weight: bold;
    color: #3d2c2c;
  }

  .service-meta {
    font-size: 7pt;
    color: #9e8070;
    margin-top: 0.5mm;
  }

  .service-qty {
    float: right;
    font-size: 8pt;
    color: #c9a96e;
    font-weight: bold;
  }

  /* QR Code */
  .qr-section {
    text-align: center;
    padding: 4mm 12mm;
    background: #f9f6f2;
  }

  .qr-section img {
    width: 28mm;
    height: 28mm;
  }

  .qr-label {
    font-size: 6.5pt;
    color: #9e8070;
    margin-top: 2mm;
  }

  /* Total */
  .total-section {
    text-align: center;
    padding: 3mm 12mm;
    background: #f9f6f2;
    border-top: 0.5pt solid #e8ddd0;
  }

  .total-label { font-size: 6.5pt; color: #9e8070; letter-spacing: 1pt; text-transform: uppercase; }
  .total-value { font-size: 14pt; font-weight: bold; color: #3d2c2c; }

  /* Rodapé */
  .footer {
    background: #3d2c2c;
    padding: 5mm 12mm;
    text-align: center;
    position: absolute;
    bottom: 0;
    width: 100%;
  }

  .footer p {
    font-size: 7pt;
    color: #c9a96e80;
    margin: 1mm 0;
    letter-spacing: 0.5pt;
  }
</style>
</head>
<body>
<div class="card">

  <!-- Cabeçalho -->
  <div class="top-band">
    <div class="brand">Day Spa Hochheim</div>
    <div class="ornament">
      <div class="ornament-line"></div>
      <div class="ornament-dot"></div>
      <div class="ornament-line"></div>
    </div>
    <div class="brand-sub">Cartão Presente</div>
  </div>

  <!-- Código -->
  <div class="code-section">
    <div class="code-label">Código do Cartão</div>
    <div class="code"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="validity">Válido até <?= $validUntilFmt ?></div>
  </div>

  <!-- Serviços incluídos -->
  <div class="body-section">
    <div class="section-title">Serviços incluídos</div>
    <?php foreach ($giftCardItems as $item): ?>
      <div class="service-item">
        <span class="service-qty">x<?= (int) $item['quantity'] ?></span>
        <div class="service-name"><?= htmlspecialchars($item['service_name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="service-meta"><?= htmlspecialchars($item['service_category'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= (int) $item['service_duration'] ?> min</div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Total -->
  <div class="total-section">
    <div class="total-label">Valor do Presente</div>
    <div class="total-value"><?= $totalFormatted ?></div>
  </div>

  <!-- QR Code -->
  <?php if ($qrCodeBase64): ?>
  <div class="qr-section">
    <img src="<?= $qrCodeBase64 ?>" alt="QR Code">
    <div class="qr-label">Escaneie para validar</div>
  </div>
  <?php endif; ?>

  <!-- Rodapé -->
  <div class="footer">
    <p>Rua Itaiópolis, 102 — Blumenau, SC &bull; (47) 3037-1707</p>
    <p>Seg–Sex: 13h às 21h &bull; hochheim.com.br</p>
  </div>

</div>
</body>
</html>
