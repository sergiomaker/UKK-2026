
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Me</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e3a8a);
            color: white;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            max-width: 850px;
            width: 100%;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 40px;
            display: flex;
            align-items: center;
            gap: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .profile {
            width: 180px;
            height: 180px;
            flex-shrink: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, #38bdf8, #818cf8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 70px;
            border: 5px solid rgba(255, 255, 255, 0.3);
        }

        .content h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .content h1 span {
            color: #38bdf8;
        }

        .content h3 {
            color: #cbd5e1;
            margin-bottom: 18px;
        }

        .content p {
            color: #e2e8f0;
            line-height: 1.8;
            margin-bottom: 20px;
        }

        .skills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
        }

        .skills span {
            background: rgba(56, 189, 248, 0.15);
            color: #7dd3fc;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 13px;
        }

        .button {
            display: inline-block;
            text-decoration: none;
            background: #38bdf8;
            color: #0f172a;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: bold;
            transition: 0.3s;
        }

        .button:hover {
            background: white;
            transform: translateY(-3px);
        }

        @media (max-width: 600px) {
            .container {
                flex-direction: column;
                text-align: center;
                padding: 30px 20px;
            }

            .content h1 {
                font-size: 30px;
            }

            .skills {
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="profile">👨‍💻</div>

        <div class="content">
            <h1>About <span>Me</span></h1>
            <h3>Halo! Saya REIHAN Muzaki👋</h3>

            <p>
                Saya adalah seorang pelajar yang tertarik
                dengan dunia teknologi dan pemrograman.
                Saya senang mempelajari hal baru,
                membuat website, dan mengembangkan
                kemampuan saya di bidang teknologi.
            </p>

            <div class="skills">
                <span>HTML</span>
                <span>CSS</span>
                <span>JavaScript</span>
                <span>Creative</span>
            </div>

            <a href="mailto:emailkamu@gmail.com" class="button">
                Hubungi Saya
            </a>
        </div>
    </div>

</body>
</html>
