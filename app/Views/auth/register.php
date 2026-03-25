<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base-url" content="<?= env('app.apiBaseURL', base_url()) ?>">
    <meta name="app-web-base-url" content="<?= base_url() ?>">
    <title>Registro de usuario</title>
    <link rel="stylesheet" href="<?= base_url(PUBLIC_FOLDER . "assets/css/styles.css") ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
    <script src="https://kit.fontawesome.com/9bae38f407.js" crossorigin="anonymous"></script>
    <link rel="icon" href="<?= base_url(PUBLIC_FOLDER . "assets/images/favicon.ico") ?>" type="image/x-icon">
    <style>
        body.register-screen {
            min-height: 100vh;
            background:
                radial-gradient(900px 420px at -5% -10%, rgba(22, 94, 204, .18), transparent 60%),
                radial-gradient(900px 300px at 110% 0%, rgba(255, 160, 66, .12), transparent 58%),
                linear-gradient(180deg, #f7f3e7 0%, #eef4fb 100%);
            color: #1e1e1e;
        }

        .register-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 16px;
        }

        .register-card {
            width: min(980px, 100%);
            border-radius: 28px;
            overflow: hidden;
            background: rgba(255, 255, 255, .92);
            border: 1px solid rgba(177, 212, 240, .8);
            box-shadow: 0 26px 70px rgba(16, 52, 90, .16);
            backdrop-filter: blur(12px);
        }

        .register-grid {
            display: grid;
            grid-template-columns: 1.05fr .95fr;
        }

        .register-hero {
            background:
                linear-gradient(160deg, rgba(22, 94, 204, .95) 0%, rgba(10, 38, 70, .96) 100%);
            color: #f7f3e7;
            padding: 42px 38px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 680px;
            position: relative;
        }

        .register-hero::after {
            content: "";
            position: absolute;
            inset: auto -80px -120px auto;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 160, 66, .34) 0%, rgba(255, 160, 66, 0) 72%);
        }

        .register-kicker {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(247, 243, 231, .12);
            border: 1px solid rgba(247, 243, 231, .18);
            font-size: .92rem;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .register-hero h1 {
            font-size: clamp(2rem, 3.2vw, 3.1rem);
            line-height: 1.02;
            font-weight: 700;
            margin: 18px 0 12px;
        }

        .register-hero p {
            color: rgba(247, 243, 231, .82);
            font-size: 1rem;
            max-width: 34ch;
            margin: 0;
        }

        .register-points {
            list-style: none;
            display: grid;
            gap: 14px;
            padding: 0;
            margin: 28px 0 0;
        }

        .register-points li {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            color: rgba(247, 243, 231, .9);
        }

        .register-points i {
            color: #ffa042;
            margin-top: 4px;
        }

        .register-brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            padding: 14px 18px;
            border-radius: 20px;
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .08);
            backdrop-filter: blur(10px);
        }

        .register-brand img {
            width: min(240px, 100%);
        }

        .register-form-pane {
            padding: 40px 34px 34px;
        }

        .register-form-pane h2 {
            font-size: 1.9rem;
            font-weight: 700;
            color: #17324d;
            margin-bottom: 10px;
        }

        .register-form-note {
            color: #5e6772;
            margin-bottom: 22px;
        }

        .register-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .register-select {
            margin-bottom: 18px;
        }

        .register-form-pane .form-control,
        .register-form-pane .form-select {
            min-height: 54px;
            border-radius: 14px;
        }

        .register-form-pane .form-check {
            padding: 12px 14px 12px 2rem;
            border: 1px solid #d8e6f4;
            border-radius: 14px;
            background: #f8fbff;
        }

        .register-form-pane .btn {
            border-radius: 14px;
            min-height: 48px;
            font-weight: 600;
        }

        .register-form-pane .btn-brand {
            min-width: 150px;
        }

        body.theme-dark.register-screen {
            background:
                radial-gradient(900px 420px at -5% -10%, rgba(22, 94, 204, .24), transparent 60%),
                radial-gradient(900px 300px at 110% 0%, rgba(255, 160, 66, .10), transparent 58%),
                linear-gradient(180deg, #0c1826 0%, #0f2133 100%);
            color: #dbe9f8;
        }

        body.theme-dark .register-card {
            background: rgba(18, 34, 51, .92);
            border-color: #33506e;
            box-shadow: 0 26px 70px rgba(0, 0, 0, .34);
        }

        body.theme-dark .register-hero {
            background: linear-gradient(160deg, rgba(11, 34, 54, .98) 0%, rgba(18, 49, 74, .98) 100%);
        }

        body.theme-dark .register-form-pane h2 {
            color: #f7f3e7;
        }

        body.theme-dark .register-form-note {
            color: #aac2da;
        }

        body.theme-dark .register-form-pane .form-check {
            background: #193047;
            border-color: #33506e;
        }

        @media (max-width: 900px) {
            .register-grid {
                grid-template-columns: 1fr;
            }

            .register-hero {
                min-height: auto;
                gap: 28px;
            }
        }
    </style>
</head>

<body class="register-screen">
    <script>
        (function () {
            try {
                if (localStorage.getItem('reservas_theme') === 'dark') {
                    document.body.classList.add('theme-dark');
                }
            } catch (e) {}
        })();
    </script>

    <div class="register-shell">
        <div class="register-card">
            <div class="register-grid">
                <section class="register-hero">
                    <div>
                        <span class="register-kicker">
                            <i class="fa-solid fa-user-shield"></i>
                            Panel de usuarios
                        </span>
                        <h1>Crear y editar accesos del panel sin salir del flujo admin.</h1>
                        <p>La lógica sigue siendo la misma. Solo cambié la presentación para que quede más cerca de AlfaReservas.</p>

                        <ul class="register-points">
                            <li>
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Alta rápida de usuarios nuevos con permisos de superadmin si corresponde.</span>
                            </li>
                            <li>
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span>Edición inmediata desde el selector superior usando los mismos IDs que ya consume tu JavaScript.</span>
                            </li>
                            <li>
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>Visual más clara, con mejor lectura en claro y oscuro.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="register-brand">
                        <a href="<?= base_url() ?>">
                            <img src="<?= base_url(PUBLIC_FOLDER . "assets/images/logo.png") ?>" alt="La Barca">
                        </a>
                    </div>
                </section>

                <section class="register-form-pane">
                    <h2>Registro de usuario</h2>
                    <p class="register-form-note">Podés crear uno nuevo o seleccionar uno existente para editarlo.</p>

                    <div class="register-select">
                        <label class="form-label" for="selectUser">Usuario existente</label>
                        <select class="form-select" name="users" id="selectUser" aria-label="Seleccionar usuario">
                            <option>Seleccionar usuario</option>
                            <?php if (isset($users)) : ?>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?= $user['id'] ?>"><?= $user['name'] ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <form action="" method="POST" id="formUsers">
                        <?php if (session('msg')) : ?>
                            <div class="alert alert-<?= session('msg.type') ?> alert-dismissible fade show" role="alert">
                                <small><?= session('msg.body') ?></small>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="form-floating mb-3">
                            <input type="text" name="user" class="form-control" id="user" placeholder="Usuario">
                            <label for="user">Usuario</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="password" name="password" class="form-control" id="password" placeholder="Contraseña">
                            <label for="password">Contraseña</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="password" name="repeat_password" class="form-control" id="repeat_password" placeholder="Repetir contraseña">
                            <label for="repeat_password">Repetir contraseña</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" name="name" class="form-control" id="name" placeholder="Nombre visible">
                            <label for="name">Nombre visible</label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="superadmin" id="superadminRadio">
                            <label class="form-check-label" for="superadminRadio">
                                Superadmin
                            </label>
                        </div>

                        <div class="register-actions">
                            <a href="<?= base_url('abmAdmin') ?>" class="btn btn-muted-action">Volver</a>
                            <button type="submit" class="btn btn-brand" id="btn-login">Registrar</button>
                        </div>
                    </form>

                    <form action="" method="POST" id="formselectUser"></form>

                    <div class="register-actions d-none" id="formButtons">
                        <button type="submit" class="btn btn-brand" id="buttonEdit">Actualizar</button>
                        <a href="<?= base_url('abmAdmin') ?>" class="btn btn-muted-action">Volver</a>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script src="<?= base_url(PUBLIC_FOLDER . "assets/js/config.js") ?>"></script>
    <script src="<?= base_url(PUBLIC_FOLDER . "assets/js/users.js") ?>"></script>
</body>

</html>
