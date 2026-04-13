<?php
/** @var array $order */
$appUrl = env('APP_URL', 'https://hochheim.com.br');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pagamento não aprovado — Day Spa Hochheim</title>
<style>
  body { margin:0; padding:0; background:#f5f0eb; font-family:'Helvetica Neue',Arial,sans-serif; color:#3d2c2c; }
  .wrapper { max-width:600px; margin:0 auto; background:#fff; }
  .header { background:#3d2c2c; padding:40px; text-align:center; }
  .header h1 { color:#c9a96e; margin:0; font-size:22px; font-weight:300; letter-spacing:4px; text-transform:uppercase; }
  .icon { text-align:center; padding:32px 0 0; font-size:48px; }
  .body { padding:24px 40px 40px; }
  .body h2 { font-size:20px; font-weight:400; margin:0 0 8px; text-align:center; }
  .body p  { font-size:14px; line-height:1.7; color:#5a4a4a; margin:0 0 16px; }
  .reasons { background:#fff8f0; border-radius:8px; padding:20px; margin:20px 0; }
  .reasons h3 { font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#c9a96e; margin:0 0 12px; }
  .reasons ul { margin:0; padding-left:18px; }
  .reasons li { font-size:13px; color:#5a4a4a; margin:6px 0; line-height:1.5; }
  .cta { display:block; background:#c9a96e; color:#fff !important; text-decoration:none; text-align:center; padding:14px 28px; border-radius:6px; font-size:14px; font-weight:600; letter-spacing:1px; margin:24px 0; }
  .footer { background:#f5f0eb; padding:24px 40px; text-align:center; }
  .footer p { font-size:12px; color:#9e8070; margin:4px 0; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Day Spa Hochheim</h1>
  </div>

  <div class="icon">❌</div>

  <div class="body">
    <h2>Pagamento não aprovado</h2>
    <p style="text-align:center;">Olá, <strong><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong>. Infelizmente seu pagamento referente ao pedido <strong>#<?= $order['id'] ?></strong> não foi aprovado.</p>

    <div class="reasons">
      <h3>Causas comuns</h3>
      <ul>
        <li>Saldo insuficiente no cartão ou conta</li>
        <li>Dados do cartão incorretos ou expirado</li>
        <li>Limite de crédito atingido</li>
        <li>Transação bloqueada pelo banco emissor</li>
      </ul>
    </div>

    <p>Você pode tentar novamente usando outro cartão, Pix, ou entrar em contato conosco para ajudar a concluir sua compra.</p>

    <a href="<?= htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8') ?>/checkout?order=<?= $order['id'] ?>" class="cta">Tentar Novamente</a>

    <p style="font-size:12px;color:#9e8070;text-align:center;">Dúvidas? Fale conosco pelo (47) 3037-1707 ou WhatsApp.</p>
  </div>

  <div class="footer">
    <p>Day Spa Hochheim — Rua Itaiópolis, 102, Blumenau – SC</p>
    <p>(47) 3037-1707 &bull; Seg–Sex: 13h às 21h</p>
  </div>
</div>
</body>
</html>
