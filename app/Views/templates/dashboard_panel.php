<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base-url" content="<?= env('app.apiBaseURL', base_url()) ?>">
    <meta name="app-web-base-url" content="<?= base_url() ?>">
    <script>
        (function () {
            try {
                if (localStorage.getItem('reservas_theme') === 'dark') {
                    document.addEventListener('DOMContentLoaded', function () {
                        document.body.classList.add('theme-dark');
                    });
                }
            } catch (e) {}
        })();
    </script>
    <?php echo $this->renderSection('title') ?>
    <title>Home</title>

    <link rel="icon" href="<?= base_url(PUBLIC_FOLDER . "assets/images/favicon.ico") ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js" integrity="sha384-oBqDVmMz9ATKxIep9tiCxS/Z9fNfEXiDAYTujMAeBAsjFuCZSmKbSSUnQlmh/jp3" crossorigin="anonymous"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.min.js" integrity="sha384-cuYeSxntonz0PPNlHhBs68uyIAVpIIOZZ5JqeqvYYIcEL727kskC66kF92t6Xl2V" crossorigin="anonymous"></script> -->
    <link rel="stylesheet" href="<?= base_url(PUBLIC_FOLDER . "assets/css/theme.css?v=" . time()) ?>">
    <link rel="stylesheet" href="<?= base_url(PUBLIC_FOLDER . "assets/css/styles.css") ?>">
    <?php echo $this->renderSection('styles') ?>
    <script src="https://kit.fontawesome.com/9bae38f407.js" crossorigin="anonymous"></script>

</head>

<body class="site-body <?php echo trim((string) $this->renderSection('bodyClass')) ?>">
    <?php echo $this->renderSection('navbar') ?>
    <nav class="navbar navbar-expand-lg site-navbar" style="background-color: #ffffff;">
        <div class="container-fluid d-flex justify-content-center align-items-center flex-row">
            <div class="d-flex justify-content-center align-items-center flex-row">
                
                <div class="mx-auto d-lg-none"> <!-- Centra en dispositivos móviles -->
                    <a class="navbar-brand" href="<?= base_url() ?>">
                        <img src="<?= base_url(PUBLIC_FOLDER . "assets/images/logo.png") ?>" width="150px" alt="">
                    </a>
                </div>

                <div class="mx-auto d-none d-lg-block"> <!-- Centra en pantalla grande -->
                    <a class="navbar-brand" href="<?= base_url() ?>">
                        <img src="<?= base_url(PUBLIC_FOLDER . "assets/images/logo.png") ?>" width="200px" alt="">
                    </a>
                </div>

                <?php if (session()->logueado) : ?>
                    <span class="me-1"><?= session()->name ?></span>
                    <a href="<?= base_url('auth/logOut') ?>" class="btn btn-danger me-1" type="button" id=""><i class="fa-solid fa-plug-circle-xmark"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </nav>


    <?php echo $this->renderSection('content') ?>


    <?php echo $this->renderSection('footer') ?>

    <div class="container-fluid">
        <footer class="my-4 site-footer" style="background-color: #5a5a5a;">
            <?php if (session()->logueado) : ?>
                <ul class="nav justify-content-center border-bottom pb-3 mb-3">
                    <li class="nav-item"><a href="<?= base_url('auth/logOut') ?>" class="nav-link px-2 text-muted">Cerrar sesion</a></li>
                    <li class="nav-item"><a href="<?= base_url('abmAdmin') ?>" class="nav-link px-2 text-muted">Panel</a></li>
                </ul>
            <?php else : ?>
                <ul class="nav justify-content-center border-bottom pb-3 mb-3">
                    <li class="nav-item"><a href="<?= base_url('auth/login') ?>" class="nav-link px-2 text-muted">Ingreso Admin</a></li>
                    <li class="nav-item"><a class="nav-link px-2 text-muted">-</a></li>
                    <li class="nav-item"><a href="<?= base_url('customers/register') ?>" class="nav-link px-2 text-muted js-open-public-customer-register" data-register-url="<?= base_url('customers/registerWindow') ?>">Registro Clientes</a></li>
                </ul>
            <?php endif; ?>

                        <div class="link d-flex justify-content-center align-items-center">
                            <a href="https://alfagestion.com.ar/" target="_blank" class="text-center text-muted">(c) 2023 - Alfanet</a>
                        </div>
                    </footer>
                </div>

                <?php if (empty($suppressPublicRegisterModal)) : ?>
                    <?php $publicRegisterScriptPath = FCPATH . 'assets/js/customerRegisterPublic.js'; ?>
                    <div class="modal fade public-customer-register-modal" id="publicCustomerRegisterModal" tabindex="-1" aria-labelledby="publicCustomerRegisterModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="publicCustomerRegisterModalLabel">Registro de clientes</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body p-0">
                                    <iframe id="publicCustomerRegisterFrame" class="public-customer-register-frame" title="Registro de clientes"></iframe>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php echo $this->renderSection('scripts') ?>

                <?php if (empty($suppressPublicRegisterModal)) : ?>
                    <script src="<?= base_url(PUBLIC_FOLDER . 'assets/js/customerRegisterPublic.js?v=' . (is_file($publicRegisterScriptPath) ? filemtime($publicRegisterScriptPath) : time())) ?>"></script>
                <?php endif; ?>

                <script src="<?= base_url(PUBLIC_FOLDER . "assets/js/config.js?v=" . time()) ?>"></script>
                <script src="<?= base_url(PUBLIC_FOLDER . "assets/js/theme.js?v=" . time()) ?>"></script>
                <script>
                    let sessionUserId = <?= json_encode(session()->id_user) ?>;
        let sessionUserLogued = <?= json_encode(session()->logueado) ?>;
        let sessionUserSuperadmin = <?= json_encode(session()->superadmin) ?>;
    </script>
</body>

</html>
