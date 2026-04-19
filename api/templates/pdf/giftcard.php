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
    color: #004567;
    width: 148mm;
    min-height: 210mm;
  }

  .card {
    width: 148mm;
    min-height: 210mm;
    background: #ffffff;
    position: relative;
    overflow: hidden;
  }

  /* Faixa superior */
  .top-band {
    background: #004567;
    padding: 18mm 12mm 14mm;
    text-align: center;
    position: relative;
  }

  .brand {
    font-size: 11pt;
    letter-spacing: 2pt;
    text-transform: uppercase;
    color: #FDFEFF;
    margin-bottom: 2mm;
    font-weight: bold;
  }

  .brand-sub {
    font-size: 7pt;
    letter-spacing: 2pt;
    color: #92DEFD;
    text-transform: uppercase;
  }

  /* Ornamento decorativo */
  .ornament {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3mm;
    margin: 4mm 0;
  }
  .ornament-line { width: 30mm; height: 1pt; background: #92DEFD; display: inline-block; }

  /* Código do cartão */
  .code-section {
    background: #F8F8F8;
    margin: 8mm 10mm;
    border-radius: 4mm;
    padding: 8mm 10mm;
    text-align: center;
    border: 1pt solid #1B7C9F;
  }

  .code-label {
    font-size: 7pt;
    letter-spacing: 1pt;
    text-transform: uppercase;
    color: #1B7C9F;
    margin-bottom: 2mm;
    font-weight: bold;
  }

  .code {
    font-size: 18pt;
    font-weight: bold;
    color: #004567;
    letter-spacing: 4pt;
    font-family: Helvetica, sans-serif;
  }

  .validity {
    font-size: 7pt;
    color: #1B7C9F;
    margin-top: 3mm;
  }

  /* Corpo */
  .body-section {
    padding: 4mm 12mm 6mm;
  }

  .section-title {
    font-size: 8pt;
    letter-spacing: 1pt;
    text-transform: uppercase;
    color: #1B7C9F;
    border-bottom: 1pt solid #92DEFD;
    padding-bottom: 2mm;
    margin-bottom: 4mm;
    font-weight: bold;
  }

  /* Serviços */
  .service-item {
    padding: 3mm 0;
    border-bottom: 0.5pt solid #E5F3F8;
  }

  .service-item:last-child { border-bottom: none; }

  .service-name {
    font-size: 9pt;
    font-weight: bold;
    color: #004567;
  }

  .service-meta {
    font-size: 7.5pt;
    color: #1B7C9F;
    margin-top: 1mm;
  }

  .service-qty {
    float: right;
    font-size: 9pt;
    color: #004567;
    font-weight: bold;
  }

  /* QR Code */
  .qr-section {
    text-align: center;
    padding: 6mm 12mm;
    background: #F8F8F8;
  }

  .qr-section img {
    width: 32mm;
    height: 32mm;
    border: 1pt solid #92DEFD;
    padding: 2mm;
    background: #ffffff;
    border-radius: 4px;
  }

  .qr-label {
    font-size: 7pt;
    color: #1B7C9F;
    margin-top: 3mm;
    font-weight: bold;
  }

  /* Total */
  .total-section {
    text-align: center;
    padding: 4mm 12mm;
    background: #F8F8F8;
    border-top: 1pt solid #92DEFD;
    border-bottom: 1pt solid #92DEFD;
    margin: 4mm 0;
  }

  .total-label { font-size: 7pt; color: #1B7C9F; letter-spacing: 1pt; text-transform: uppercase; font-weight: bold; }
  .total-value { font-size: 14pt; font-weight: bold; color: #004567; margin-top: 1mm; }

  /* Rodapé */
  .footer {
    background: #004567;
    padding: 6mm 12mm;
    text-align: center;
    position: absolute;
    bottom: 0;
    width: 100%;
  }

  .footer p {
    font-size: 7.5pt;
    color: #F8F8F8;
    margin: 1.5mm 0;
    letter-spacing: 0.5pt;
  }
</style>
</head>
<body>
<div class="card">

  <!-- Cabeçalho -->
  <div class="top-band">
    <div class="brand">Clínica Hochheim SPA</div>
    <div class="ornament">
      <div class="ornament-line"></div>
    </div>
    <div class="brand-sub">Cartão Presente</div>
  </div>

  <!-- Código -->
  <div class="code-section">
    <div class="code-label">Código Único de Validação</div>
    <div class="code"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="validity">Utilize até <?= $validUntilFmt ?></div>
  </div>

  <!-- Serviços incluídos -->
  <div class="body-section">
    <div class="section-title">Experiências incluídas</div>
    <?php foreach ($giftCardItems as $item): ?>
      <div class="service-item">
        <span class="service-qty">x<?= (int) $item['quantity'] ?></span>
        <div class="service-name"><?= htmlspecialchars($item['service_name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="service-meta"><?= htmlspecialchars($item['service_category'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= (int) $item['service_duration'] ?> min</div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Total
  <div class="total-section">
    <div class="total-label">Valor do Presente</div>
    <div class="total-value"><?= $totalFormatted ?></div>
  </div>
  -->

  <!-- QR Code -->
  <?php if ($qrCodeBase64): ?>
  <div class="qr-section">
    <img src="<?= $qrCodeBase64 ?>" alt="QR Code">
    <div class="qr-label">Apresente este código no dia</div>
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
