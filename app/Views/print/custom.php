<?php
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$decodedTemplate = html_entity_decode((string) $templateContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$normalizedTemplate = preg_replace('/[\x00-\x1F\x7F]+/', '', $decodedTemplate);
$unsafeTemplate = preg_match('~<\s*/?\s*(?:script|iframe|object|embed|applet|base|link|meta|form|input|button|textarea|select|option|svg|math)\b~i', $normalizedTemplate)
    || preg_match('~\son[a-z0-9_-]+\s*=~i', $normalizedTemplate)
    || preg_match('~\b(?:href|src|xlink:href|action|formaction)\s*=\s*(?:"|\')?\s*(?:javascript|vbscript|data)\s*:~i', $normalizedTemplate)
    || preg_match('~(?:expression\s*\(|url\s*\(\s*(?:"|\')?\s*(?:javascript|data)\s*:|@import|-moz-binding)~i', $normalizedTemplate);

if ($unsafeTemplate) {
    $templateContent = \App\Helpers\TemplateHelper::getDefaultTemplate();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Voucher</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { 
                margin: 0; 
                padding: 0; 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important; 
            }
            * {
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important; 
            }
        }
        body { margin: 0; padding: 0; background: #eee; font-family: sans-serif; }
        .voucher-wrapper {
             /* Wrapper for page break control if needed */
             display: inline-block;
             margin: 5px;
             page-break-inside: avoid;
        }
    </style>
    <script src="/assets/js/qrious.min.js"></script>
</head>
<body>
    <?php include __DIR__.'/toolbar.php'; ?>

    <div style="padding: 20px; text-align: center;">
        <?php foreach ($users as $index => $u) { ?>
            <div class="voucher-wrapper">
                <?php
                    $html = $templateContent;
            // Replace Variables
            // Standard variables
            $replacements = [
                '{{username}}' => $escape($u['username'] ?? ''),
                '{{password}}' => $escape($u['password'] ?? ''),
                '{{price}}' => $escape($u['price'] ?? ''),
                '{{validity}}' => $escape($u['validity'] ?? ''),
                '{{timelimit}}' => $escape($u['timelimit'] ?? $u['validity'] ?? ''),
                '{{time_limit}}' => $escape($u['timelimit'] ?? $u['validity'] ?? ''),
                '{{datalimit}}' => $escape($u['datalimit'] ?? ''),
                '{{data_limit}}' => $escape($u['datalimit'] ?? ''),
                '{{profile}}' => $escape($u['profile'] ?? ''),
                '{{comment}}' => $escape($u['comment'] ?? ''),
                '{{hotspotname}}' => $escape($u['hotspotname'] ?? ''),
                '{{server_name}}' => $escape($u['hotspotname'] ?? ''),
                '{{dns_name}}' => $escape($u['dns_name'] ?? ''),
                '{{login_url}}' => $escape($u['login_url'] ?? ''),
                '{{num}}' => (string) ($index + 1),
                '{{logo}}' => '<img src="/assets/img/logo.png" style="height:30px;border:0;">', // Default Logo placeholder
            ];

            // 1. Handle {{logo id=...}}
            $html = preg_replace_callback('/\{\{logo\s+id=[\'"]?([^\'"\s]+)[\'"]?\}\}/i', function ($matches) use ($escape, $logoMap) {
                $id = $matches[1];
                if (isset($logoMap[$id])) {
                    return '<img src="'.$escape($logoMap[$id]).'" style="height:50px; width:auto;">'; // Default style, user can wrap in div
                }

                return ''; // Return empty if not found
            }, $html);

            foreach ($replacements as $key => $val) {
                $html = str_replace($key, $val, $html);
            }

            // 2. Handle QR Code with Logo support
            $html = preg_replace_callback('/\{\{qrcode(?:\s+(.*?))?\}\}/i', function ($matches) use ($index, $u, $logoMap) {
                $qrId = 'qr-custom-'.$index.'-'.uniqid();
                $qrCodeValue = ($u['login_url'] ?? '').'?user='.($u['username'] ?? '').'&password='.($u['password'] ?? '');

                // Default Options
                $opts = [
                    'value' => $qrCodeValue,
                    'size' => 100,
                    'foreground' => 'black',
                    'background' => 'white',
                    'padding' => 0,
                    'rounded' => 0,
                    'logo' => null, // Logo ID
                ];

                // Parse Attributes
                if (! empty($matches[1])) {
                    $attrs = $matches[1];
                    if (preg_match('/fg\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $attrs, $m)) {
                        $opts['foreground'] = preg_match('/^(?:#[0-9a-f]{3,8}|[a-z]+)$/i', $m[1]) ? $m[1] : 'black';
                    }
                    if (preg_match('/bg\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $attrs, $m)) {
                        $opts['background'] = preg_match('/^(?:#[0-9a-f]{3,8}|[a-z]+)$/i', $m[1]) ? $m[1] : 'white';
                    }
                    if (preg_match('/size\s*=\s*[\'"]?(\d+)[\'"]?/i', $attrs, $m)) {
                        $opts['size'] = max(40, min(1000, (int) $m[1]));
                    }
                    if (preg_match('/padding\s*=\s*[\'"]?(\d+)[\'"]?/i', $attrs, $m)) {
                        $opts['padding'] = min(200, (int) $m[1]);
                    }
                    if (preg_match('/rounded\s*=\s*[\'"]?(\d+)[\'"]?/i', $attrs, $m)) {
                        $opts['rounded'] = min(200, (int) $m[1]);
                    }
                    if (preg_match('/logo\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $attrs, $m)) {
                        $opts['logo'] = $m[1];
                    }
                }

                // CSS Styles
                $cssPadding = $opts['padding'] ? ('padding: '.$opts['padding'].'px; ') : '';
                $cssBg = 'background-color: '.$opts['background'].'; ';
                $rounded = $opts['rounded'] ? ('border-radius: '.$opts['rounded'].'px;') : '';
                $baseStyle = 'display: inline-block; vertical-align: middle; '.$cssBg.$cssPadding.$rounded;
                $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;

                // JS Generation
                $qrJs = '(function() { var qr = new QRious({' .
                    'element: document.getElementById('.json_encode($qrId, $jsonFlags).'),' .
                    'value: '.json_encode($opts['value'], $jsonFlags).',' .
                    'size: '.$opts['size'].',' .
                    'foreground: '.json_encode($opts['foreground'], $jsonFlags).',' .
                    'backgroundAlpha: 0});';

                // If Logo is requested and found
                if ($opts['logo'] && isset($logoMap[$opts['logo']])) {
                    $logoPath = $logoMap[$opts['logo']];
                    $qrJs .= 'var img = new Image();' .
                        'img.src = '.json_encode($logoPath, $jsonFlags).';' .
                        'img.onload = function() {' .
                        'var canvas = document.getElementById('.json_encode($qrId, $jsonFlags).');' .
                        'var ctx = canvas.getContext("2d");' .
                        'var size = '.$opts['size'].';' .
                        'var logoSize = size * 0.2;' .
                        'var logoPos = (size - logoSize) / 2;' .
                        'ctx.drawImage(img, logoPos, logoPos, logoSize, logoSize);};';
                }

                $qrJs .= '})();';

                return '<canvas id="'.$qrId.'" style="'.$baseStyle.'"></canvas><script>'.$qrJs.'</script>';
            }, $html);

            echo $html;
            ?>
            </div>
        <?php } ?>
    </div>
</body>
</html>
