/*LOGICA SIDEBAR Y MENUS*/
const menu = document.getElementById('menu');
const sidebar = document.getElementById('sidebar');
const main = document.getElementById('main');

menu.addEventListener('click', () => {
    sidebar.classList.toggle('menu-toggle');
    menu.classList.toggle('menu-toggle');
    main.classList.toggle('menu-toggle');
});

document.querySelectorAll('.toggle').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        this.parentElement.classList.toggle('active');
    });
});
/*LOGICA PLAN DE BATALLA */