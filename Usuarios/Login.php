
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.2);
        }

        .login-title {
            font-weight: bold;
            color: #333;
        }

        .form-control {
            padding: 12px;
            border-radius: 8px;
        }

        .btn-login {
            padding: 12px;
            border-radius: 8px;
            font-weight: bold;
        }

        .login-footer {
            font-size: 14px;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="login-card mx-auto">

            <div class="text-center mb-4">
                <h2 class="login-title">Bem-vindo!</h2>
                <p class="text-muted">Faça login para continuar</p>
            </div>

            <form>
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input
                        type="email"
                        class="form-control"
                        placeholder="Digite seu e-mail"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Senha</label>
                    <input
                        type="password"
                        class="form-control"
                        placeholder="Digite sua senha"
                        required>
                </div>

                <div class="d-flex justify-content-between mb-4">
                    <div class="form-check">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            id="lembrar">
                        <label class="form-check-label" for="lembrar">
                            Lembrar-me
                        </label>
                    </div>

                    <a href="#" class="text-decoration-none">
                        Esqueceu a senha?
                    </a>
                </div>

                <button type="submit"
                    class="btn btn-primary btn-login w-100">
                    Entrar
                </button>
            </form>

            <div class="text-center mt-4 login-footer">
                <span class="text-muted">Não tem uma conta?</span>
                <a href="#" class="text-decoration-none fw-bold">
                    Cadastre-se
                </a>
            </div>

        </div>
    </div>

</body>
</html>
