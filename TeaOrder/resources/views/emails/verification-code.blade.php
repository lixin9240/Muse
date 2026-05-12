{{-- resources/views/emails/verification-code.blade.php --}}
{{-- 会员激活验证码邮件模板 --}}

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会员激活验证码</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', 'PingFang SC', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
        }
        .message {
            color: #666;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        .code-box {
            background-color: #f8f9fa;
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            margin: 30px 0;
        }
        .code-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .code {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }
        .warning {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 15px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #999;
            font-size: 14px;
        }
        .shop-name {
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧋 {{ $shopName }}</h1>
        </div>
        
        <div class="content">
            <div class="greeting">
                尊敬的 <strong>{{ $customerName }}</strong> 您好：
            </div>
            
            <div class="message">
                感谢您注册 <span class="shop-name">{{ $shopName }}</span> 会员！
            </div>
            
            <div class="code-box">
                <div class="code-label">您的验证码是：</div>
                <div class="code">{{ $verificationCode }}</div>
                <div class="warning">
                    ⏰ 有效期5分钟，请勿泄露给他人。
                </div>
            </div>
            
            <div class="message" style="font-size: 14px; color: #999;">
                如非本人操作，请忽略此邮件。
            </div>
        </div>
        
        <div class="footer">
            <div class="shop-name">{{ $shopName }}</div>
            <div>{{ $date }}</div>
        </div>
    </div>
</body>
</html>