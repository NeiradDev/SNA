<?php
// LINKS DE SIDEBARD
$menuItems = [
    [
        'title' => 'Dashboard',
        'icon'  => 'bi-graph-up',
        'id'    => 'submenu-dashboard',
        'sub'   => ['Mi Equipo', 'Mi División']
    ],

    [
        'title' => 'Reporte',
        'icon'  => 'bi-clipboard2-data',
        'id'    => 'submenu-reporte',
        'sub'   => ['Plan de Batalla', 'Historico']
    ],
    [
        'title' => 'Agencias',
        'icon'  => 'bi-houses',
        'url'   => '/agencias'
    ],
    [
        'title' => 'Areas',
        'icon'  => 'bi-grid',
        'id'    => 'submenu-areas',
        'sub'   => ['Tics', 'Contabilidad', 'Talento Humano']
    ],
    [
        'title' => 'Usuarios',
        'icon'  => 'bi-people',
        'url'   => '/usuarios'
    ],
];
?>

<aside class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-dark" aria-label="Menú principal">
    <div class="d-flex flex-column align-items-center align-items-sm-start px-3 pt-2 text-white min-vh-100">
        
        <a href="<?= base_url('/home') ?>" class="d-flex align-items-center pb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <span class="fs-5 d-none d-sm-inline fw-bold">Home</span>
        </a>

        <nav class="w-100">
            <ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start" id="menu">
                
                <?php foreach ($menuItems as $item): ?>
                    <li class="nav-item w-100">
                        <?php if (isset($item['sub'])): ?>
                            <a href="#<?= $item['id'] ?>" data-bs-toggle="collapse" class="nav-link px-0 align-middle text-white">
                                <i class="fs-4 <?= $item['icon'] ?>"></i> 
                                <span class="ms-1 d-none d-sm-inline"><?= $item['title'] ?></span>
                            </a>
                            <ul class="collapse nav flex-column ms-3" id="<?= $item['id'] ?>" data-bs-parent="#menu">
                                <?php foreach ($item['sub'] as $subItem): ?>
                                    <li class="w-100">
                                        <a href="#" class="nav-link px-0 text-white-50 small font-monospace"> 
                                            <span class="d-none d-sm-inline">> </span><?= $subItem ?> 
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <a href="<?= base_url($item['url']) ?>" class="nav-link px-0 align-middle text-white">
                                <i class="fs-4 <?= $item['icon'] ?>"></i> 
                                <span class="ms-1 d-none d-sm-inline"><?= $item['title'] ?></span>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>

            </ul>
        </nav>

        <hr class="w-100 border-secondary">

        <div class="dropdown pb-4 w-100">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?= base_url('assets/img/img-login.png') ?>" alt="User" width="30" height="30" class="rounded-circle shadow-sm">
                <span class="d-none d-sm-inline mx-2">Mi perfil</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                <li><a class="dropdown-item" href="#">Perfil</a></li>
                <li><hr class="dropdown-divider border-secondary"></li>
                <li><a class="dropdown-item" href="<?= base_url('logout/') ?>">Salir</a></li>
            </ul>
        </div>
    </div>
</aside>