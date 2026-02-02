<!-- CSS (si tu layout no lo incluye) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<!-- jQuery (si tu layout no lo trae globalmente) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- JS -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
  // Inicializador parametrizable
  function initDataTable(selector, columnDefs = []) {
    $(selector).DataTable({
      responsive: true,
      pageLength: 10,
      lengthMenu: [10, 25, 50, 100],
      order: [
        [0, 'desc']
      ], // primera col (oculta) por ID desc
      dom: "<'row align-items-center g-2'<'col-12 col-md-6 d-flex flex-wrap gap-2'B><'col-12 col-md-6 d-flex justify-content-md-end'f>>" +
        "<'row'<'col-12'tr>>" +
        "<'row align-items-center g-2'<'col-12 col-md-5'i><'col-12 col-md-7 d-flex justify-content-md-end'p>>",
      buttons: [{
          extend: 'excel',
          text: '<i class="bi bi-file-earmark-excel me-1"></i>Excel',
          className: 'btn btn-sm btn-dark',
          exportOptions: {
            columns: ':not(.no-export)'
          }
        },
        {
          extend: 'colvis',
          text: '<i class="bi bi-layout-three-columns me-1"></i>Columnas',
          className: 'btn btn-sm btn-outline-dark'
        }
      ],
      columnDefs: [{
          targets: 0,
          visible: false,
          searchable: false
        }, // ID oculto
        {
          targets: -1,
          orderable: false,
          searchable: false,
          className: 'no-export'
        }, // acciones
        ...columnDefs
      ],
      language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json",
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_",
        info: "Mostrando _START_ a _END_ de _TOTAL_",
        infoEmpty: "Mostrando 0 a 0 de 0",
        zeroRecords: "No se encontraron resultados",
        paginate: {
          first: "Primero",
          last: "Último",
          next: "Siguiente",
          previous: "Anterior"
        }
      },
      autoWidth: false
    });
  }
</script>