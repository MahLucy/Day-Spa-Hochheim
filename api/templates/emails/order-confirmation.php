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
<title>Pedido Recebido — Clínica Hochheim SPA</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@200;400;500;600;700&display=swap');
  body { margin:0; padding:0; background:#9F9D9D; font-family: 'Poppins', Arial, sans-serif; color:#004567; }
  .wrapper { max-width:600px; margin:20px auto; background:#FFFFFF; border-radius:12px; overflow:hidden; }
  .header { background:#004567; padding:32px 40px; text-align:center; }
  .header img { max-width:180px; margin-bottom:10px; }
  .header h1 { color:#FDFEFF; margin:0; font-size:22px; font-weight:500; letter-spacing:2px; text-transform:uppercase; }
  .header p  { color:#92DEFD; margin:4px 0 0; font-size:12px; letter-spacing:1px; font-weight:200; }
  .body { padding:40px; }
  .body h2 { font-size:22px; font-weight:600; color:#004567; margin:0 0 12px; }
  .body p  { font-size:14px; line-height:1.7; color:#1B7C9F; margin:0 0 16px; font-weight:400; }
  .order-box { background:#F8F8F8; border-left:4px solid #92DEFD; border-radius:0 8px 8px 0; padding:20px; margin:24px 0; }
  .order-box p { margin:4px 0; font-size:14px; color:#004567; }
  .order-box strong { font-weight:600; }
  table.items { width:100%; border-collapse:collapse; margin:16px 0; }
  table.items th { text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:#1B7C9F; padding:10px 0; border-bottom:2px solid #92DEFD; }
  table.items td { font-size:14px; padding:12px 0; border-bottom:1px solid #E5F3F8; color:#004567; font-weight:400; }
  table.items td.price { text-align:right; font-weight:600; }
  .total-row td { font-size:16px; font-weight:700; color:#004567; padding-top:16px; }
  .footer { background:#004567; padding:32px 40px; text-align:center; }
  .footer p { font-size:12px; color:#F8F8F8; margin:4px 0; font-weight:200; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Clínica Hochheim">
    <h1>Pedido Recebido</h1>
    <p>Aguardando pagamento</p>
  </div>

  <div class="body">
    <h2>Olá, <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>!</h2>
    <p>Recebemos seu pedido. Complete o pagamento no Mercado Pago para que seu cartão presente seja gerado e enviado.</p>

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
          <td colspan="2">Total a pagar</td>
          <td class="price">R$ <?= number_format((float) $order['total'], 2, ',', '.') ?></td>
        </tr>
      </tbody>
    </table>

    <p style="font-size:13px; margin-top:20px;">Após a confirmação do pagamento, você receberá o cartão presente no e-mail <strong><?= htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8') ?></strong>.</p>
  </div>

  <div class="footer">
    <p>Clínica Hochheim SPA — Blumenau – SC</p>
    <p>(47) 3037-1707 &bull; contato@hochheim.com.br</p>
    <p style="margin-top:12px;color:#92DEFD;">Seu momento de cuidado e bem-estar.</p>
  </div>
</div>
</body>
</html>
