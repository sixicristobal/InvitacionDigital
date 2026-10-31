<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú" aria-controls="adminSidebar" aria-expanded="false">☰ Menú</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="adminSidebar">
    <h2>💍 Boda</h2>
    <nav>
        <a href="<?= ADMIN_URL ?>/index.php">Dashboard</a>
        <a href="<?= ADMIN_URL ?>/parejas.php">Parejas</a>
        <a href="<?= ADMIN_URL ?>/invitados.php">Invitados</a>
        <a href="<?= ADMIN_URL ?>/asistencia.php">Asistencia y Mesas</a>
        <a href="<?= ADMIN_URL ?>/exportar.php">Exportar (Excel/PDF)</a>
        <a href="<?= ADMIN_URL ?>/logout.php">Cerrar sesión</a>
    </nav>
</aside>
<script data-purpose="menu-responsive">
(function () {
    var sidebar = document.getElementById('adminSidebar');
    var toggle = document.getElementById('menuToggle');
    var overlay = document.getElementById('sidebarOverlay');
    if (!sidebar || !toggle) return;
    function closeMenu() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        toggle.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('no-scroll');
    }
    toggle.addEventListener('click', function () {
        var open = sidebar.classList.toggle('open');
        overlay.classList.toggle('show', open);
        toggle.classList.toggle('active', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('no-scroll', open);
        toggle.innerHTML = open ? '✕ Cerrar' : '☰ Menú';
    });
    overlay.addEventListener('click', closeMenu);
    sidebar.querySelectorAll('nav a').forEach(function (a) { a.addEventListener('click', closeMenu); });
})();
</script>
