<?php
/** @var array $order */
$appUrl  = env('APP_URL', 'https://hochheim.com.br');
$logoUrl = $appUrl . '/logo.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pagamento não aprovado —  Day Spa Hochheim</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@200;400;500;600;700&display=swap');
  body { margin:0; padding:0; background:#9F9D9D; font-family:'Poppins', Arial, sans-serif; color:#004567; }
  .wrapper { max-width:600px; margin:20px auto; background:#FFFFFF; border-radius:12px; overflow:hidden; box-shadow:0 12px 40px rgba(0,0,0,.15); }
  .header { background:#004567; padding:32px 40px; text-align:center; }
  .header img { max-width:180px; margin-bottom:10px; }
  .icon { text-align:center; padding:40px 0 0; font-size:48px; }
  .body { padding:24px 40px 40px; }
  .body h2 { font-size:22px; font-weight:600; margin:0 0 12px; text-align:center; color:#E53E3E; }
  .body p  { font-size:14px; line-height:1.7; color:#1B7C9F; margin:0 0 16px; text-align:center; }
  .reasons { background:#FFF5F5; border-radius:8px; padding:20px; margin:24px 0; border: 1px solid #FED7D7; }
  .reasons h3 { font-size:13px; text-transform:uppercase; letter-spacing:1px; color:#C53030; margin:0 0 12px; font-weight:600; }
  .reasons ul { margin:0; padding-left:20px; }
  .reasons li { font-size:14px; color:#C53030; margin:8px 0; line-height:1.5; font-weight:500; }
  .cta { display:block; background:#1B7C9F; color:#FDFEFF !important; text-decoration:none; text-align:center; padding:16px 28px; border-radius:30px; font-size:15px; font-weight:600; letter-spacing:1px; margin:32px 0 24px; transition:all 0.3s; }
  .footer { background:#004567; padding:32px 40px; text-align:center; }
  .footer p { font-size:12px; color:#F8F8F8; margin:4px 0; font-weight:200; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Clínica Hochheim">
  </div>

  <div class="icon">❌</div>

  <div class="body">
    <h2>Pagamento não aprovado</h2>
    <p>Olá, <strong><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong>. Infelizmente seu pagamento referente ao pedido <strong>#<?= $order['id'] ?></strong> não foi aprovado pelo Mercado Pago.</p>

    <div class="reasons">
      <h3>Possíveis causas sugeridas:</h3>
      <ul>
        <li>Saldo insuficiente no cartão ou conta</li>
        <li>Dados do cartão incorretos ou expirado</li>
        <li>Transação bloqueada pelo banco emissor (tente ligar no banco)</li>
        <li>Pagamento via PIX não realizado no prazo estimado</li>
      </ul>
    </div>

    <p style="color:#004567;">Você pode tentar novamente usando outro cartão, Pix, ou entrar em contato conosco para ajudar a concluir sua compra.</p>

    <a href="<?= htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8') ?>/checkout?order=<?= $order['id'] ?>" class="cta">Tentar Novamente</a>

    <p style="font-size:13px;color:#1B7C9F;font-weight:600;">Dúvidas? Fale conosco pelo (47) 3037-1707 ou pelo WhatsApp <a href="https://wa.me/5547991151707">(47) 99115-1707</a>.</p>
  </div>

  <div class="footer">
    <p>Day Spa Hochheim — Blumenau – SC</p>
    <p>(47) 3037-1707 &bull; Seg–Sex: 13h às 21h</p>
  </div>
</div>
</body>
</html>
