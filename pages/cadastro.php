<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cadastro</title>
    <link rel="stylesheet" href="../CSS/cadastro.css">
</head>
<body>
    <div class="container">
        <div class="parent">
            <div class="div1">
                <h1>bem vindo(a)!</h1>
                <p>preencha as informações a seguir e se cadastre:</p>
                <img src="../imagens/body_care.png" alt="logo" class="logo">
            </div>
            <div class="div2">
                <form>
                    <input type="text" placeholder="nome completo">
                    <br>
                    <input type="number" placeholder="telefone">
                    <br>
                    <input type="email" placeholder="e-mail">
                    <br>
                    <input type="password" placeholder="senha">

                    <button type="submit" onclick="alert('cadastro realizado com sucesso!')">enviar cadastro</button>
                </form>
            </div>
        </div>
    </div>    
</body>
</html>