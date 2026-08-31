<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>login</title>
    <link rel="stylesheet" href="CSS/style.css">
</head>
<body>
    <div class="container">
        <h1>bem vindo(a) novamente!</h1>
        <p>preencha as informações a seguir e realize seu login:</p>
        <form>
            <input type="email" placeholder="e-mail">
            <br>
            <input type="password" placeholder="senha">
            <br>
            <button type="submit">logar</button>
        </form>

        <div class="divisor">
            <span>ou</span>
        </div>
        
        <p class="texto-login">não possui uma conta? <br> faça cadastro para acessar sua área:</p>
            <a href="./pages/cadastro.php" class="btn-login">fazer cadastro</a>
    </div>    
</body>
</html>