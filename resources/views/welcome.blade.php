<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <title>Votus | API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            /* margin: 0; */
            /* overflow: hidden; */
            align-items: center;
            justify-content: center;
            background: #ffffff;
            font-family: 'Montserrat', sans-serif;
        }

        .container {
            text-align: center;
            /* position: absolute; */
        }

        .logo {
            width: 180px;
            margin-bottom: 60px;
        }


        .status {
            color: #333;
            font-size: 18px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <img
            src="https://github.com/user-attachments/assets/391c325b-9cb0-4998-a657-c7f587cbefa9"
            alt="Logo do projeto"
            style="max-width: 300px;"
        >
        <p class="status">API operacional</p>
    </div>

    <script>
        // efeito de flutuação suave
        const container = document.querySelector('.container');

        let start = null;

        function float(timestamp) {
            if (!start) start = timestamp;

            const elapsed = timestamp - start;

            const offset = Math.sin(elapsed / 1000) * 10;

            container.style.transform = `translateY(${offset}px)`;

            requestAnimationFrame(float);
        }

        requestAnimationFrame(float);

        // efeito do DVD antigo
        // const container = document.querySelector('.container');

        // let x = 100;
        // let y = 100;

        // let speedX = 1.5;
        // let speedY = 1.5;

        // function animate() {
        //     const maxX = window.innerWidth - container.offsetWidth;
        //     const maxY = window.innerHeight - container.offsetHeight;

        //     x += speedX;
        //     y += speedY;

        //     if (x <= 0 || x >= maxX) {
        //         speedX *= -1;
        //     }

        //     if (y <= 0 || y >= maxY) {
        //         speedY *= -1;
        //     }

        //     container.style.transform = `translate(${x}px, ${y}px)`;

        //     requestAnimationFrame(animate);
        // }

        // animate();
    </script>
</body>
</html>
