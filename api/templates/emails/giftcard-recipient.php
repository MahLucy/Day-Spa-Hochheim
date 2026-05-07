<?php
/** @var array $order    */
/** @var array $giftCard */
/** @var array $items    */
/** @var string $qrCid   CID do QR Code embutido */
$appUrl       = env('APP_URL', 'https://hochheim.com.br');
$logoUrl      = $appUrl . '/logo.png';
$validateUrl  = $appUrl . '/validar/' . $giftCard['code'];
$validUntil   = date('d/m/Y', strtotime($giftCard['valid_until']));
$recipientName = htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8');
$senderName    = htmlspecialchars($order['sender_name'] ?? 'alguém especial', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Você ganhou um Cartão Presente — Day Spa Hochheim</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@200;400;500;600;700&display=swap');
  body { margin:0; padding:0; background:#9F9D9D; font-family:'Poppins', Arial, sans-serif; color:#004567; }
  .wrapper { max-width:600px; margin:20px auto; background:#FFFFFF; border-radius:12px; overflow:hidden; }

  /* Hero celebratório */
  .hero { background:linear-gradient(135deg, #004567 0%, #1B7C9F 100%); padding:48px 40px 32px; text-align:center; }
  .hero .congrats { font-size:40px; margin:0 0 8px; }
  .hero h1 { color:#FDFEFF; margin:0 0 6px; font-size:26px; font-weight:700; letter-spacing:1px; }
  .hero .subtitle { color:#92DEFD; margin:0; font-size:13px; letter-spacing:1px; font-weight:300; }
  .hero img { max-width:130px; margin-bottom:20px; opacity:.9; }

  /* Cartão com código */
  .gift-card { margin:0 24px; background:#1B7C9F; border-radius:12px; padding:28px; text-align:center; margin-top:-20px; position:relative; box-shadow:0 8px 32px rgba(0,0,0,.18); }
  .gift-card .code { font-size:28px; font-weight:700; color:#FDFEFF; letter-spacing:6px; font-family:monospace; margin:0 0 8px; }
  .gift-card .validity { font-size:12px; color:#FDFEFF; opacity:.8; margin:0; letter-spacing:1px; }

  /* Corpo */
  .body { padding:32px 40px; }
  .body h2 { font-size:20px; font-weight:600; margin:0 0 12px; color:#004567; }
  .body p  { font-size:14px; line-height:1.7; color:#1B7C9F; margin:0 0 16px; }

  /* Mensagem de quem presenteou */
  .sender-box { background:#EAF6FB; border-left:4px solid #1B7C9F; border-radius:0 8px 8px 0; padding:16px 20px; margin:20px 0; }
  .sender-box p { margin:0; font-size:14px; color:#004567; font-style:italic; }
  .sender-box .from { margin-top:8px; font-style:normal; font-size:12px; color:#1B7C9F; font-weight:600; }

  /* Serviços */
  .services { background:#F8F8F8; border-radius:8px; padding:20px; margin:20px 0; border:1px solid #E5F3F8; }
  .services h3 { font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#1B7C9F; margin:0 0 12px; font-weight:600; }
  .service-item { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #E5F3F8; font-size:14px; }
  .service-item:last-child { border-bottom:none; }
  .service-name { color:#004567; font-weight:500; }
  .service-meta { color:#1B7C9F; font-size:12px; margin-top:2px; }

  /* QR */
  .qr-section { text-align:center; padding:24px 0; }
  .qr-section img { width:160px; height:160px; border:4px solid #FDFEFF; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,.08); }
  .qr-section p { font-size:12px; color:#1B7C9F; margin:12px 0 0; font-weight:500; }

  /* CTA */
  .cta { display:block; background:#1B7C9F; color:#FDFEFF !important; text-decoration:none; text-align:center; padding:16px 28px; border-radius:30px; font-size:15px; font-weight:600; letter-spacing:1px; margin:20px 0; }

  /* Footer */
  .footer { background:#004567; padding:32px 40px; text-align:center; }
  .footer p { font-size:12px; color:#F8F8F8; margin:4px 0; font-weight:200; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="hero">
    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Day Spa Hochheim">
    <div class="congrats">🎁</div>
    <h1>Parabéns, <?= $recipientName ?>!</h1>
    <p class="subtitle">Você ganhou um Cartão Presente do Day Spa Hochheim</p>
  </div>

  <div class="gift-card">
    <p class="code"><?= htmlspecialchars($giftCard['code'], ENT_QUOTES, 'UTF-8') ?></p>
    <p class="validity">Válido até <?= $validUntil ?></p>
  </div>

  <div class="body">
    <h2>Uma experiência única te espera!</h2>
    <p>
      Você foi presenteado(a) com um cartão de bem-estar no <strong>Day Spa Hochheim</strong>.
      Apresente o código ou o QR Code ao chegar à clínica para aproveitar todos os serviços incluídos.
    </p>

    <div class="sender-box">
      <p>✨ Este presente foi enviado com muito carinho por</p>
      <p class="from">— <?= $senderName ?></p>
    </div>

    <div class="services">
      <h3>Serviços incluídos no seu presente</h3>
      <?php foreach ($items as $item): ?>
        <div class="service-item">
          <div>
            <div class="service-name"><?= htmlspecialchars($item['service_name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="service-meta"><?= htmlspecialchars($item['service_category'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= (int) $item['service_duration'] ?> min</div>
          </div>
          <div style="font-weight:600;color:#004567;">x<?= (int) $item['quantity'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($qrCid): ?>
    <div class="qr-section">
      <img src="cid:<?= htmlspecialchars($qrCid, ENT_QUOTES, 'UTF-8') ?>" alt="QR Code do Cartão">
      <p>Escaneie para validar seu cartão presente</p>
    </div>
    <?php endif; ?>

    <a href="<?= htmlspecialchars($validateUrl, ENT_QUOTES, 'UTF-8') ?>" class="cta">Ver meu Cartão Online</a>

    <p style="font-size:12px;color:#1B7C9F;text-align:center;margin-top:24px;">
      <?php if (!empty($giftCard['pdf_path'])): ?>
        O PDF do cartão está anexo a este e-mail. <?php endif; ?>
      Válido por 6 meses a partir da compra.
    </p>
  </div>

  <div class="footer">
    <p>Day Spa Hochheim — Blumenau – SC</p>
    <p>(47) 3037-1707 &bull; Seg–Sex: 13h às 21h</p>
  </div>

</div>
</body>
</html>
