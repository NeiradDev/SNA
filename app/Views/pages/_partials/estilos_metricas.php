<?php
// app/Views/pages/_partials/estilos_metricas.php
// part: 'css' | 'js' | 'all'
$part = $part ?? 'all';
?>

<?php if ($part === 'css' || $part === 'all'): ?>
  <style>
    .chart-card {
      background: #fff;
      height: 260px;  
      border: 1px solid #eee;
      border-radius: 10px;
      padding: 12px;
      margin-top: 12px;
    }

    .chart-grid {
      display: flex;
      gap: 14px;
      align-items: stretch;
      flex-wrap: wrap;
    }

    .kpi-card {
      width: 260px;
      max-width: 100%;
      border: 1px solid #eee;
      border-radius: 10px;
      padding: 12px;
      background: #fafafa;
    }

    .kpi-title {
      font-size: 12px;
      color: #666;
      margin: 0 0 6px 0;
    }

    .kpi-value {
      font-size: 34px;
      font-weight: 800;
      line-height: 1;
      margin: 0;
    }

    .kpi-sub {
      font-size: 12px;
      color: #666;
      margin-top: 4px;
    }

    .kpi-row {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px dashed #ddd;
      color: #333;
      gap: 10px;
    }

    .kpi-row small {
      color: #666;
    }

    .chart-area {
      flex: 1;
      min-width: 280px;
      border: 1px solid #eee;
      border-radius: 10px;
      background: #fff;
      padding: 12px;
      min-height: 190px;
    }

    .chart-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 8px;
      flex-wrap: wrap;
    }

    .chart-head h3 {
      margin: 0;
      font-size: 16px;
    }

    .month-filter {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: #333;
      flex-wrap: wrap;
    }

    .month-filter input[type="month"] {
      padding: 6px 8px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #fff;
      font-size: 12px;
    }

    .month-filter button {
      padding: 6px 10px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #fff;
      cursor: pointer;
      font-size: 12px;
    }

    .month-filter button:hover {
      background: #f2f2f2;
    }
  </style>
<?php endif; ?>


<?php if ($part === 'js' || $part === 'all'): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>

  <script defer>
    (() => {
      "use strict";

      // =========================
      // DOM
      // =========================
      const canvas = document.getElementById("lineChart");
      const monthPicker = document.getElementById("monthPicker");
      const btnThisMonth = document.getElementById("btnThisMonth");
      if (!canvas || !monthPicker) return;

      const weeklyPercent = document.getElementById("weeklyPercent");
      const weeklyHint = document.getElementById("weeklyHint");
      const bestWeek = document.getElementById("bestWeek");
      const worstWeek = document.getElementById("worstWeek");
      const weeksCount = document.getElementById("weeksCount");

      // =========================
      // Utilidades de fecha
      // =========================
      const WD = ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"];
      const MO = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

      const pad2 = (n) => String(n).padStart(2, "0");
      const yyyymm = (d) => `${d.getFullYear()}-${pad2(d.getMonth()+1)}`;
      const fmtWed = (d) => `${WD[d.getDay()]} ${d.getDate()} ${MO[d.getMonth()]}`;

      const parseMonthInput = (v) => {
        if (!v || !v.includes("-")) return null;
        const [yy, mm] = v.split("-").map(Number);
        if (!yy || !mm) return null;
        return {
          y: yy,
          m: mm - 1
        };
      };

      const addDays = (d, n) => {
        const x = new Date(d);
        x.setDate(x.getDate() + n);
        return x;
      };

      const startOfMonth = (y, m) => new Date(y, m, 1);
      const endOfMonth = (y, m) => new Date(y, m + 1, 0, 23, 59, 59);

      const wednesdaysInMonth = (y, m) => {
        const first = startOfMonth(y, m);
        const last = endOfMonth(y, m);
        const target = 3; // miércoles

        const offset = (target - first.getDay() + 7) % 7;
        let cur = addDays(first, offset);

        const arr = [];
        while (cur <= last) {
          arr.push(new Date(cur));
          cur = addDays(cur, 7);
        }
        return arr;
      };

      const clampMonthToNow = (monthValue) => {
        const nowVal = yyyymm(new Date());
        if (!monthValue) return nowVal;
        return monthValue > nowVal ? nowVal : monthValue;
      };

      // =========================
      // DEMO: % estable por fecha (solo para ejemplo)
      // =========================
      const stablePercentForDate = (d) => {
        const key = d.getFullYear() * 10000 + (d.getMonth() + 1) * 100 + d.getDate();
        let x = key ^ 0x9e3779b9;
        x = (x ^ (x << 13)) >>> 0;
        x = (x ^ (x >> 17)) >>> 0;
        x = (x ^ (x << 5)) >>> 0;
        return 45 + (x % 51); // 45..95
      };

      const getDataDemo = (y, m) => {
        const weds = wednesdaysInMonth(y, m);

        const labels = weds.map((endWed) => {
          const startWed = addDays(endWed, -7);
          return `${fmtWed(startWed)} → ${fmtWed(endWed)}`;
        });

        const values = weds.map(stablePercentForDate);
        return {
          labels,
          values
        };
      };

      // =========================
      // KPIs + Chart
      // =========================
      const setKpis = (values, y, m) => {
        if (!values.length) {
          weeklyPercent && (weeklyPercent.textContent = "0%");
          weeklyHint && (weeklyHint.textContent = "Sin datos para este mes.");
          bestWeek && (bestWeek.textContent = "-");
          worstWeek && (worstWeek.textContent = "-");
          weeksCount && (weeksCount.textContent = "0");
          return;
        }

        const avg = Math.round(values.reduce((a, b) => a + b, 0) / values.length);
        weeklyPercent && (weeklyPercent.textContent = `${avg}%`);
        weeklyHint && (weeklyHint.textContent = `Mes: ${MO[m]} ${y} (Miércoles: ${values.length})`);
        bestWeek && (bestWeek.textContent = `${Math.max(...values)}%`);
        worstWeek && (worstWeek.textContent = `${Math.min(...values)}%`);
        weeksCount && (weeksCount.textContent = String(values.length));
      };

      let chart = null;

      const renderChart = (labels, values) => {
        if (chart) chart.destroy();

        chart = new Chart(canvas, {
          type: "line",
          data: {
            labels,
            datasets: [{
              label: "Cumplimiento",
              data: values,
              tension: 0.3,
              fill: false,
              pointRadius: 3
            }]
          },
          options: {
            responsive: true,
            plugins: {
              legend: {
                display: true
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                min: 0,
                max: 100,
                ticks: {
                  callback: (v) => v + "%"
                }
              }
            }
          }
        });
      };

      // =========================
      // Update (HOY usa DEMO)
      // =========================
      const update = () => {
        monthPicker.value = clampMonthToNow(monthPicker.value);
        const parsed = parseMonthInput(monthPicker.value);
        if (!parsed) return;

        const {
          y,
          m
        } = parsed;
        const {
          labels,
          values
        } = getDataDemo(y, m);

        setKpis(values, y, m);
        renderChart(labels, values);
      };

      // Init
      const nowVal = yyyymm(new Date());
      monthPicker.max = nowVal;
      if (!monthPicker.value) monthPicker.value = nowVal;

      monthPicker.addEventListener("change", update);
      btnThisMonth && btnThisMonth.addEventListener("click", () => {
        monthPicker.value = nowVal;
        update();
      });

      const waitChart = () => (window.Chart ? update() : setTimeout(waitChart, 30));
      waitChart();
    })();
  </script>

  <!-- =========================================================
     FUTURO: MÉTRICAS POR API (USUARIO LOGUEADO) — TODO COMENTADO
     =========================================================
<script defer>
(() => {
  "use strict";

  // RESUMEN:
  // - Frontend pide /api/metricas/cumplimiento?month=YYYY-MM
  // - Backend toma el usuario desde sesión (logged_in + user_id)
  // - Backend devuelve SOLO la métrica del usuario logueado (labels/values)

  async function getDataFromApiForLoggedUser(yyyyMM) {
    const url = `<?= base_url('api/metricas/cumplimiento') ?>?month=${encodeURIComponent(yyyyMM)}`;

    const res = await fetch(url, {
      method: "GET",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      // credentials: "include" // si luego usas subdominio
    });

    if (res.status === 401) throw new Error("No autorizado: sesión expirada o usuario no logueado.");
    if (!res.ok) throw new Error(`API error: ${res.status}`);

    const json = await res.json();
    if (!json || !Array.isArray(json.labels) || !Array.isArray(json.values)) {
      throw new Error("Formato inválido: se esperan labels[] y values[]");
    }
    return { labels: json.labels, values: json.values };
  }

  // EJEMPLO de update() con API (debes hacer update async):
  //
  // const update = async () => {
  //   monthPicker.value = clampMonthToNow(monthPicker.value);
  //   const parsed = parseMonthInput(monthPicker.value);
  //   if (!parsed) return;
  //
  //   try {
  //     const { labels, values } = await getDataFromApiForLoggedUser(monthPicker.value);
  //     setKpis(values, parsed.y, parsed.m);
  //     renderChart(labels, values);
  //   } catch (e) {
  //     console.error(e);
  //   }
  // };
})();
</script>
-->
<?php endif; ?>