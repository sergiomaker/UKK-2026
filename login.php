<?php
session_start();
?>

<!DOCTYPE html>
<html>
<head>

    <title>Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous">

</head>

<body>
   

 <div class="container d-flex justify-content-center align-items-center vh-100">

    <div class="card shadow"
         style="width: 320px; height: 380px; border-radius: 12px;">

        <div class="card-body p-4">

            <h2 class="text-center mb-4">LOGIN</h2>

            <form action="proses_login.php" method="POST">

                <div class="mb-3 text-start">
                    <label class="form-label">Email</label>
                    <input type="text"
                           name="email"
                           class="form-control">
                </div>

                <div class="mb-4 text-start">
                    <label class="form-label">Password</label>
                    <input type="password"
                           name="password"
                           class="form-control">
                </div>

                <div class="text-center">
                    <button type="submit"
                            name="login"
                            value="Login"
                            class="btn btn-success w-100">
                        LOGIN
                    </button>
                </div>

            </form>

        </div>

    </div>

</div>
</body>
</html>