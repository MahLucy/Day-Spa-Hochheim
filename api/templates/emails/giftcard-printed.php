<?php
/** @var array $order    */
/** @var array $giftCard */
/** @var array $items    */
$appUrl     = env('APP_URL', 'https://hochheim.com.br');
$logoUrl    = $appUrl . '/logo.png';
$validUntil = date('d/m/Y', strtotime($giftCard['valid_until']));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cartão Presente Impresso — Clínica Hochheim SPA</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@200;400;500;600;700&display=swap');
  body { margin:0; padding:0; background:#9F9D9D; font-family:'Poppins', Arial, sans-serif; color:#004567; }
  .wrapper { max-width:600px; margin:20px auto; background:#FFFFFF; border-radius:12px; overflow:hidden; box-shadow:0 12px 40px rgba(0,0,0,.15); }
  .header { background:#004567; padding:40px; text-align:center; }
  .header img { max-width:160px; margin-bottom:12px; }
  .header h1 { color:#FDFEFF; margin:0 0 4px; font-size:22px; font-weight:500; letter-spacing:2px; text-transform:uppercase; }
  .header p  { color:#92DEFD; margin:0; font-size:12px; letter-spacing:1px; font-weight:200; }
  .body { padding:40px; }
  .body h2 { font-size:20px; font-weight:600; margin:0 0 12px; color:#004567; }
  .body p  { font-size:14px; line-height:1.7; color:#1B7C9F; margin:0 0 16px; font-weight:400; }
  .info-box { background:#F8F8F8; border-left:4px solid #1B7C9F; padding:20px 24px; margin:24px 0; border-radius:0 8px 8px 0; }
  .info-box p { margin:6px 0; font-size:14px; color:#004567; }
  .step { display:flex; gap:16px; margin:16px 0; align-items:flex-start; }
  .step-num { background:#1B7C9F; color:#FDFEFF; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:600; flex-shrink:0; text-align:center; line-height:28px; }
  .step-text { font-size:14px; color:#1B7C9F; padding-top:4px; line-height:1.6; }
  .step-text strong { color:#004567; font-weight:600; }
  .services { background:#F8F8F8; border-radius:8px; padding:20px; margin:24px 0; border: 1px solid #E5F3F8; }
  .services h3 { font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#1B7C9F; margin:0 0 12px; font-weight:600; }
  .service-item { padding:10px 0; border-bottom:1px solid #E5F3F8; font-size:14px; color:#1B7C9F; }
  .service-item strong { color:#004567; font-weight:500; }
  .service-item:last-child { border-bottom:none; }
  .footer { background:#004567; padding:32px 40px; text-align:center; }
  .footer p { font-size:12px; color:#F8F8F8; margin:4px 0; font-weight:200; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Clínica Hochheim">
    <h1>Day Spa Hochheim</h1>
    <p>Confirmação — Cartão Presente Impresso</p>
  </div>

  <div class="body">
    <h2>Olá, <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>!</h2>
    <p>Seu pedido de <strong>cartão presente impresso</strong> foi confirmado. Veja as informações abaixo e acompanhe os próximos passos para a retirada na clínica.</p>

    <div class="info-box">
      <p><strong>Pedido:</strong> #<?= $order['id'] ?></p>
      <p><strong>Código do cartão:</strong> <span style="font-family:monospace;font-size:18px;color:#1B7C9F;font-weight:700;letter-spacing:1px;"><?= htmlspecialchars($giftCard['code'], ENT_QUOTES, 'UTF-8') ?></span></p>
      <p><strong>Válido até:</strong> <?= $validUntil ?></p>
      <p><strong>Formato:</strong> Impresso (retirada presencial)</p>
    </div>

    <div class="services">
      <h3>Serviços incluídos</h3>
      <?php foreach ($items as $item): ?>
        <div class="service-item">
          <strong><?= htmlspecialchars($item['service_name'], ENT_QUOTES, 'UTF-8') ?></strong>
          <br>
          <span style="font-size: 13px;"><?= $item['service_category'] ?>, <?= $item['service_duration'] ?> min (x<?= $item['quantity'] ?>)</span>
        </div>
      <?php endforeach; ?>
    </div>

    <h3 style="font-size:16px;font-weight:600;margin:32px 0 16px;color:#004567;">Próximos passos</h3>
    <div class="step">
      <div class="step-num">1</div>
      <div class="step-text">Entre em contato pelo <strong>(47) 3037-1707</strong> ou WhatsApp para confirmar o horário de retirada do seu cartão impresso.</div>
    </div>
    <div class="step">
      <div class="step-num">2</div>
      <div class="step-text">Passe na nossa clínica para retirar a embalagem premium com o cartão físico. Leve um documento de identificação com foto.</div>
    </div>
    <div class="step">
      <div class="step-num">3</div>
      <div class="step-text">Entregue o cartão para quem você quiser presentear! O recebedor poderá agendar qualquer serviço incluso dentro da validade de 6 meses.</div>
    </div>
  </div>

  <div class="footer">
    <p>Clínica Hochheim SPA — Blumenau – SC</p>
    <p>(47) 3037-1707 &bull; Seg–Sex: 13h às 21h</p>
  </div>
</div>
</body>
</html>
