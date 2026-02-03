<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
  // Si tu base_url incluye /public, esta ruta funciona; si no, el alterno lo corrige.
  $logoMain = base_url('assets/logo-bpc.png');
  $logoAlt  = preg_replace('#/public(/|$)#', '/', $logoMain);
?>

<style>
  :root{
    --red:#E10600;
    --black:#0B0B0B;
    --white:#FFF;
  }

  .org-wrap{ padding:12px; }
  .org-card{
    background: var(--white);
    border:1px solid rgba(0,0,0,.18);
    border-radius:16px;
    box-shadow:0 10px 20px rgba(0,0,0,.10);
    overflow:hidden;
  }
  .org-header{
    padding:14px 16px;
    background: var(--black);
    border-bottom:3px solid var(--red);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
  }
  .org-title{
    margin:0;
    font-size:18px;
    font-weight:900;
    color: var(--white);
    letter-spacing:.2px;
  }
  .org-sub{
    margin-top:4px;
    font-size:13px;
    opacity:.85;
    color: rgba(255,255,255,.82);
  }
  .btn-pdf{
    border:1px solid rgba(255,255,255,.22);
    background: var(--red);
    color: var(--white);
    padding:8px 12px;
    border-radius:10px;
    font-weight:800;
    font-size:13px;
    cursor:pointer;
    display:flex;
    align-items:center;
    gap:8px;
    user-select:none;
  }
  .btn-pdf:active{ transform: translateY(1px); }
  .btn-pdf:disabled{ opacity:.6; cursor:not-allowed; }

  .org-body{ padding:12px; background: var(--white); }

  #orgChart{
    width:100%;
    height:72vh;
    min-height:520px;
    background: var(--white);
    border:1px solid rgba(0,0,0,.16);
    border-radius:14px;
    overflow:hidden;
  }
  @media (max-width:768px){
    #orgChart{ height:75vh; min-height:480px; }
  }
</style>

<div class="org-wrap">
  <div class="org-card">
    <div class="org-header">
      <div>
        <h1 class="org-title" id="orgTitle">Organigrama</h1>
        <div class="org-sub">Estructura por área</div>
      </div>

      <button id="btnPdf" class="btn-pdf" type="button">
        <span style="font-size:14px;line-height:1">🧾</span>
        <span>Descargar PDF</span>
      </button>
    </div>

    <div class="org-body">
      <div id="orgChart"></div>
    </div>
  </div>
</div>

<!-- Export libs -->
<script src="https://cdn.jsdelivr.net/npm/html-to-image@1.11.11/dist/html-to-image.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<script type="module">
  import { OrgChart } from "https://cdn.jsdelivr.net/npm/d3-org-chart@3/+esm";

  const dataUrl = <?= json_encode($dataUrl ?? '') ?>;
  const logoCandidates = [
    <?= json_encode($logoMain) ?>,
    <?= json_encode($logoAlt) ?>,
  ];

  const elChart = document.getElementById("orgChart");
  const elTitle = document.getElementById("orgTitle");
  const btnPdf  = document.getElementById("btnPdf");

  let chart = null;
  let title = "Organigrama";

  const wait = (ms) => new Promise(r => setTimeout(r, ms));

  const escapeHtml = (v) =>
    String(v ?? "").replace(/[&<>"']/g, m => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"
    }[m]));

  const nodeTemplate = (node) => {
    const d = node.data;
    const name = escapeHtml(d.fullName);
    const cargo = d.cargo ? `<div style="font-size:12px;margin-top:3px;color:rgba(0,0,0,.78)">${escapeHtml(d.cargo)}</div>` : "";
    const area  = d.area  ? `<div style="font-size:12px;margin-top:2px;color:rgba(0,0,0,.55)">${escapeHtml(d.area)}</div>` : "";

    return `
      <div style="padding:10px 12px;border-radius:14px;background:#fff;border:1px solid rgba(0,0,0,.18);
                  box-shadow:0 6px 14px rgba(0,0,0,.10);min-width:270px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <div style="width:8px;height:44px;border-radius:999px;background:#E10600;"></div>
          <div>
            <div style="font-weight:900;color:#0B0B0B;font-size:14px;line-height:1.1">${name}</div>
            ${cargo}
            ${area}
          </div>
        </div>
      </div>
    `;
  };

  async function fetchJson(url){
    const res = await fetch(url, { headers: { "Accept":"application/json" } });
    if (!res.ok) throw new Error("No se pudo cargar el organigrama");
    return res.json();
  }

  async function loadLogoDataUrl(urls){
    // fetch->blob->base64 (evita CORS/tainted)
    for (const url of urls){
      try{
        const res = await fetch(url, { cache:"no-store" });
        if (!res.ok) continue;
        const blob = await res.blob();
        return await new Promise((resolve, reject) => {
          const r = new FileReader();
          r.onload = () => resolve(r.result);
          r.onerror = () => reject(new Error("No se pudo leer el logo"));
          r.readAsDataURL(blob);
        });
      }catch(_){}
    }
    return null;
  }

  async function toCanvasFromDataUrl(dataUrl){
    const img = await new Promise((resolve, reject) => {
      const i = new Image();
      i.onload = () => resolve(i);
      i.onerror = () => reject(new Error("No se pudo decodificar imagen capturada"));
      i.src = dataUrl;
    });
    const c = document.createElement("canvas");
    c.width  = img.naturalWidth || img.width;
    c.height = img.naturalHeight || img.height;
    const ctx = c.getContext("2d");
    ctx.fillStyle = "#FFFFFF";
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(img, 0, 0);
    return c;
  }

  async function captureChartCanvas(){
    // 1) html-to-image (mejor con SVG+foreignObject)
    if (window.htmlToImage?.toPng){
      try{
        const png = await window.htmlToImage.toPng(elChart, {
          backgroundColor: "#FFFFFF",
          pixelRatio: 2,
          cacheBust: true
        });
        return await toCanvasFromDataUrl(png);
      }catch(e){
        console.warn("html-to-image falló, usando html2canvas:", e);
      }
    }

    // 2) fallback html2canvas
    if (!window.html2canvas) throw new Error("html2canvas no está cargado (CDN/CSP).");
    return await window.html2canvas(elChart, {
      backgroundColor: "#FFFFFF",
      scale: 2,
      useCORS: true,
      foreignObjectRendering: true,
      logging: false
    });
  }

  function setBtnLoading(isLoading){
    btnPdf.disabled = isLoading;
    btnPdf.innerHTML = isLoading
      ? '<span style="font-size:14px;line-height:1">⏳</span><span>Generando…</span>'
      : '<span style="font-size:14px;line-height:1">🧾</span><span>Descargar PDF</span>';
  }

  function safeFileName(text){
    return (text || "organigrama")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/(^-|-$)/g, "");
  }

  function drawPdfHeader(pdf, dims, header, logoDataUrl, pageIndex, pagesTotal){
    const { pageW } = dims;
    const { margin, headerY, headerH } = header;

    // Fondo header
    pdf.setFillColor(255,255,255);
    pdf.rect(0, 0, pageW, headerY + headerH, "F");

    // Logo (si existe)
    if (logoDataUrl){
      pdf.addImage(logoDataUrl, "PNG", margin, headerY + 6, 120, 34);
    }

    // Título centrado
    pdf.setTextColor(11,11,11);
    pdf.setFont("helvetica","bold");
    pdf.setFontSize(14);
    const tW = pdf.getTextWidth(title);
    pdf.text(title, (pageW - tW) / 2, headerY + 28);

    // Paginación discreta
    pdf.setFont("helvetica","normal");
    pdf.setFontSize(10);
    pdf.setTextColor(70,70,70);
    pdf.text(`Página ${pageIndex + 1} de ${pagesTotal}`, pageW - margin - 95, headerY + 28);

    // Línea roja
    pdf.setDrawColor(225,6,0);
    pdf.setLineWidth(2);
    pdf.line(margin, headerY + headerH - 12, pageW - margin, headerY + headerH - 12);
  }

  async function exportPdfMultipage(){
    if (!window.jspdf?.jsPDF) throw new Error("jsPDF no está cargado (CDN/CSP).");

    // Fit para que “quepa todo” en el contenedor antes de capturar
    if (chart?.fit){
      chart.fit();
      await wait(500);
    }

    const canvas = await captureChartCanvas();
    if (!canvas || canvas.width < 80 || canvas.height < 80) {
      throw new Error("La captura salió vacía o demasiado pequeña.");
    }

    const logoDataUrl = await loadLogoDataUrl(logoCandidates);

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ orientation: "landscape", unit: "pt", format: "a4" });

    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();

    const margin  = 24;
    const headerH = 76;
    const headerY = margin;

    const availableW = pageW - margin * 2;
    const availableH = pageH - (headerY + headerH) - margin;

    // Fit por ancho (para no cortar lateralmente)
    const imgW = canvas.width;
    const imgH = canvas.height;

    const ratio = Math.min(availableW / imgW, 1);
    const renderW = imgW * ratio;

    // X centrado real (redondeado)
    const x = Math.round(margin + (availableW - renderW) / 2);

    // Altura original por página
    const sliceHOriginal = availableH / ratio;
    const pages = Math.max(1, Math.ceil(imgH / sliceHOriginal));

    const dims = { pageW, pageH };
    const header = { margin, headerY, headerH };

    for (let i = 0; i < pages; i++){
      if (i > 0) pdf.addPage();

      drawPdfHeader(pdf, dims, header, logoDataUrl, i, pages);

      const sourceY = i * sliceHOriginal;
      const sourceH = Math.min(sliceHOriginal, imgH - sourceY);

      // Slice
      const sliceCanvas = document.createElement("canvas");
      sliceCanvas.width = imgW;
      sliceCanvas.height = Math.max(1, Math.floor(sourceH));

      const ctx = sliceCanvas.getContext("2d");
      ctx.fillStyle = "#FFFFFF";
      ctx.fillRect(0, 0, sliceCanvas.width, sliceCanvas.height);
      ctx.drawImage(canvas, 0, sourceY, imgW, sourceH, 0, 0, imgW, sourceH);

      const sliceImg = sliceCanvas.toDataURL("image/png");
      const sliceRenderH = sourceH * ratio;

      // Y centrado dentro del área disponible
      const y = Math.round((headerY + headerH) + Math.max(0, (availableH - sliceRenderH) / 2));

      pdf.addImage(sliceImg, "PNG", x, y, renderW, sliceRenderH);
    }

    pdf.save(`${safeFileName(title)}.pdf`);
  }

  async function init(){
    const payload = await fetchJson(dataUrl);
    title = payload.title ?? "Organigrama";
    elTitle.textContent = title;

    const nodes = payload.nodes ?? [];

    chart = new OrgChart()
      .container("#orgChart")
      .data(nodes)
      .nodeId(d => d.id)
      .parentNodeId(d => d.parentId)
      .nodeWidth(() => 290)
      .nodeHeight(() => 98)
      .childrenMargin(() => 45)
      .compactMarginBetween(() => 20)
      .compactMarginPair(() => 40)
      .nodeContent(nodeTemplate);

    chart.render();
    if (chart.fit) chart.fit();
  }

  btnPdf.addEventListener("click", async () => {
    setBtnLoading(true);
    try{
      await exportPdfMultipage();
    }catch(e){
      console.error("PDF error:", e);
      alert("No se pudo generar el PDF: " + (e?.message ?? "Error desconocido"));
    }finally{
      setBtnLoading(false);
    }
  });

  // Start
  init().catch(err => {
    console.error(err);
    elTitle.textContent = "Organigrama (error)";
  });
</script>

<?= $this->endSection() ?>
