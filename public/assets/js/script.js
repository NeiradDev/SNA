/*LOGICA SIDEBAR Y MENUS*/
const menu = document.getElementById('menu');
const sidebar = document.getElementById('sidebar');
const main = document.getElementById('main');
const toggles = document.querySelectorAll('.toggle');

menu.addEventListener('click', () => {
    sidebar.classList.toggle('menu-toggle');
    menu.classList.toggle('menu-toggle');
    main.classList.toggle('menu-toggle');
});

toggles.forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();

        const parent = this.parentElement;

        // abrir sidebar si está cerrado
        if (!sidebar.classList.contains('menu-toggle')) {
            sidebar.classList.add('menu-toggle');
            menu.classList.add('menu-toggle');
            main.classList.add('menu-toggle');
        }

        // cerrar otros submenus
        document.querySelectorAll('.has-sub').forEach(item => {
            if (item !== parent) {
                item.classList.remove('active');
            }
        });

        // toggle del actual
        parent.classList.toggle('active');
    });
});
/*LOGICA PLAN DE BATALLA */