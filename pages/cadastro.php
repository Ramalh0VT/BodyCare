<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
    <link rel="stylesheet" href="../CSS/cadastro.css">
</head>
<body>
    <main class="auth-shell">
        <div class="container">
            <div class="parent">
                <div class="div1">
                    <h1>bem vindo(a)!</h1>
                    <p>preencha as informações a seguir e se cadastre:</p>
                    <img src="../imagens/body_care.png" alt="logo" class="logo">
                </div>
                <div class="div2">
                    <form class="auth-form">
                        <label>Nome completo: <input type="text" name="nome" placeholder="Nome completo"></label>
                        <label>Telefone: <input type="tel" name="telefone" placeholder="Telefone"></label>
                        <label>E-mail: <input type="email" name="email" placeholder="E-mail"></label>
                        <label>Senha: <input type="password" name="senha" placeholder="Senha"></label>
                        <button type="submit" onclick="alert('cadastro realizado com sucesso!')">Enviar cadastro</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>