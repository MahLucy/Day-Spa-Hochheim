<?php
/**
 * Template HTML para geração de PDF via Dompdf (A5 landscape).
 *
 * @var string               $code
 * @var array                $order
 * @var array                $items
 * @var array                $giftCardItems
 * @var \DateTimeImmutable   $validUntil
 * @var string               $qrCodeBase64
 * @var string               $bgSrc         URI file:// ou string vazia
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  @page { margin: 0; size: A5 landscape; }

  body {
    font-family: Helvetica, Arial, sans-serif;
    margin: 0;
    padding: 0;
    width: 210mm;
    height: 148mm;
    position: relative;
  }

  .bg-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 210mm;
    height: 148mm;
    z-index: -1;
  }

  /* Lado esquerdo, verticalmente centralizado: top = (148 - 34)/2 = 57mm. */
  .qr-container {
    position: absolute;
    left: 20mm;
    top: 57mm;
    width: 34mm;
    height: 34mm;
    background: #ffffff;
    border-radius: 4px;
    padding: 2mm;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
  }

  .qr-container img {
    width: 30mm;
    height: 30mm;
    display: block;
  }
</style>
</head>
<body>
  <?php if (!empty($bgSrc)): ?>
    <img src="<?= htmlspecialchars($bgSrc, ENT_QUOTES, 'UTF-8') ?>" class="bg-image" alt="Background">
  <?php else: ?>
    <!-- Fallback caso a imagem de fundo não seja encontrada -->
    <div style="width: 100%; height: 100%; background-color: #004567; position: absolute; z-index: -1;"></div>
    <div style="position: absolute; top: 10mm; left: 10mm; color: white;">
      <h1>Cartão Presente - <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
  <?php endif; ?>

  <?php if (!empty($qrCodeBase64)): ?>
  <div class="qr-container">
    <img src="<?= $qrCodeBase64 ?>" alt="QR Code do Cartão">
  </div>
  <?php endif; ?>
</body>
</html>
