<?php
/** @var array $order */
/** @var array $items */
$appUrl  = env('APP_URL', 'https://hochheim.com.br');
$logoUrl = $appUrl . '/logo.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedido Recebido — Day Spa Hochheim</title>
<style>
  body { margin:0; padding:0; background:#f5f0eb; font-family: 'Helvetica Neue', Arial, sans-serif; color:#3d2c2c; }
  .wrapper { max-width:600px; margin:0 auto; background:#fff; }
  .header { background:#3d2c2c; padding:32px 40px; text-align:center; }
  .header h1 { color:#c9a96e; margin:0; font-size:22px; font-weight:300; letter-spacing:3px; text-transform:uppercase; }
  .header p  { color:#c9a96e80; margin:4px 0 0; font-size:12px; letter-spacing:2px; }
  .body { padding:40px; }
  .body h2 { font-size:20px; font-weight:400; color:#3d2c2c; margin:0 0 8px; }
  .body p  { font-size:14px; line-height:1.7; color:#5a4a4a; margin:0 0 16px; }
  .order-box { background:#f9f6f2; border:1px solid #e8ddd0; border-radius:8px; padding:20px; margin:24px 0; }
  .order-box p { margin:4px 0; font-size:13px; }
  .order-box strong { color:#3d2c2c; }
  table.items { width:100%; border-collapse:collapse; margin:16px 0; }
  table.items th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#9e8070; padding:8px 0; border-bottom:1px solid #e8ddd0; }
  table.items td { font-size:13px; padding:10px 0; border-bottom:1px solid #f0e8e0; color:#5a4a4a; }
  table.items td.price { text-align:right; font-weight:600; color:#3d2c2c; }
  .total-row td { font-size:15px; font-weight:700; color:#3d2c2c; padding-top:14px; }
  .cta { display:block; background:#c9a96e; color:#fff; text-decoration:none; text-align:center; padding:14px 28px; border-radius:6px; font-size:14px; font-weight:600; letter-spacing:1px; margin:24px 0; }
  .footer { background:#f5f0eb; padding:24px 40px; text-align:center; }
  .footer p { font-size:12px; color:#9e8070; margin:4px 0; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Day Spa Hochheim</h1>
    <p>Bem-estar &amp; Harmonia</p>
  </div>

  <div class="body">
    <h2>Olá, <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>!</h2>
    <p>Recebemos seu pedido. Complete o pagamento para que seu cartão presente seja gerado e enviado.</p>

    <div class="order-box">
      <p><strong>Pedido:</strong> #<?= $order['id'] ?></p>
      <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
      <p><strong>Formato:</strong> <?= $order['gift_card_format'] === 'digital' ? 'Digital' : 'Impresso' ?></p>
    </div>

    <table class="items">
      <thead>
        <tr>
          <th>Serviço</th>
          <th>Qtd</th>
          <th class="price">Valor</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <?php $snap = $item['service_snapshot']; ?>
          <tr>
            <td><?= htmlspecialchars($snap['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $item['quantity'] ?>x</td>
            <td class="price">R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="2">Total</td>
          <td class="price">R$ <?= number_format((float) $order['total'], 2, ',', '.') ?></td>
        </tr>
      </tbody>
    </table>

    <p style="font-size:13px;color:#9e8070;">Após a confirmação do pagamento, você receberá o cartão presente no e-mail <strong><?= htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8') ?></strong>.</p>
  </div>

  <div class="footer">
    <p>Day Spa Hochheim — Rua Itaiópolis, 102, Blumenau – SC</p>
    <p>(47) 3037-1707 &bull; contato@hochheim.com.br</p>
    <p style="margin-top:12px;color:#c9a96e;">Seg–Sex: 13h às 21h</p>
  </div>
</div>
</body>
</html>
