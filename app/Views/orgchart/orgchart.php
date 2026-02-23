<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

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

  .node-link{
    color: rgba(0,0,0,.80);
    text-decoration: underline;
    text-decoration-style: dotted;
  }
  .node-link:hover{
    color: #0B0B0B;
    text-decoration-style: solid;
  }
</style>

<div class="org-wrap">
  <div class="org-card">
    <div class="org-header">
      <div>
        <h1 class="org-title" id="orgTitle">Organigrama</h1>
        <div class="org-sub">Estructura por División</div>
      </div>
    </div>

    <div class="org-body">
      <div id="orgChart"></div>
    </div>
  </div>
</div>

<script type="module">
  import { OrgChart } from "https://cdn.jsdelivr.net/npm/d3-org-chart@3/+esm";

  const dataUrl = <?= json_encode($dataUrl ?? '') ?>;

  // =========================================================
  // ✅ DATOS DEL USUARIO LOGUEADO (para el mensaje de WhatsApp)
  // =========================================================
  const me = <?= json_encode($me ?? ['fullName' => '', 'cargo' => '', 'area' => '']) ?>;

  const elTitle = document.getElementById("orgTitle");
  let chart = null;

  const escapeHtml = (v) =>
    String(v ?? "").replace(/[&<>"']/g, m => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"
    }[m]));

  // =========================================================
  // ✅ Normaliza teléfono para WhatsApp (Ecuador)
  // - 0XXXXXXXXX => 593XXXXXXXXX
  // - 593XXXXXXXXX => ok
  // - XXXXXXXXX (9 dígitos) => 593XXXXXXXXX
  // =========================================================
  const normalizeEcuadorPhone = (phoneRaw) => {
    let digits = String(phoneRaw ?? "").replace(/\D/g, "");
    if (!digits) return "";

    if (digits.startsWith("0")) {
      digits = "593" + digits.slice(1);
    } else if (digits.startsWith("593")) {
      // ok
    } else if (digits.length === 9) {
      digits = "593" + digits;
    }
    return digits;
  };

  // =========================================================
  // ✅ Mensaje armado con DATOS DEL LOGUEADO (no del nodo)
  // =========================================================
  const buildLoggedUserMessage = () => {
    const nameTxt  = String(me.fullName ?? "").trim();
    const cargoTxt = String(me.cargo ?? "").trim();
    const areaTxt  = String(me.area ?? "").trim();

    // Base
    let msg = nameTxt ? `Hola, soy ${nameTxt}` : "Hola";

    // Cargo y área
    if (cargoTxt && areaTxt) {
      msg += `, ${cargoTxt} del área ${areaTxt}`;
    } else if (cargoTxt) {
      msg += `, ${cargoTxt}`;
    } else if (areaTxt) {
      msg += ` del área ${areaTxt}`;
    }

    // Cierre
    msg += `. Me comunico con usted para coordinar una información.`;

    return msg;
  };

  // =========================================================
  // ✅ Link WhatsApp con mensaje prellenado del logueado
  // =========================================================
  const toWhatsAppLinkLogged = (phoneRaw) => {
    const digits = normalizeEcuadorPhone(phoneRaw);
    if (!digits) return "";

    const msg = buildLoggedUserMessage();
    const encoded = encodeURIComponent(msg);

    return `https://wa.me/${digits}?text=${encoded}`;
  };

  const nodeTemplate = (node) => {
    const d = node.data;

    const name  = escapeHtml(d.fullName);
    const cargo = d.cargo ? `<div style="font-size:12px;margin-top:3px;color:rgba(0,0,0,.78)">${escapeHtml(d.cargo)}</div>` : "";
    const area  = d.area  ? `<div style="font-size:12px;margin-top:2px;color:rgba(0,0,0,.55)">${escapeHtml(d.area)}</div>` : "";

    // ✅ Correo clickeable (mailto)
    const email = d.email ? `
      <div style="font-size:12px;margin-top:6px;color:rgba(0,0,0,.70)">
        <b>Correo:</b>
        <a class="node-link" href="mailto:${escapeHtml(d.email)}">${escapeHtml(d.email)}</a>
      </div>` : "";

    // ✅ Teléfono -> WhatsApp con mensaje del LOGUEADO
    let phoneHtml = "";
    if (d.phone) {
      const phoneText = escapeHtml(d.phone);
      const wa = toWhatsAppLinkLogged(d.phone);

      phoneHtml = wa
        ? `
          <div style="font-size:12px;margin-top:2px;color:rgba(0,0,0,.70)">
            <b>WhatsApp:</b>
            <a class="node-link" target="_blank" rel="noopener" href="${wa}">${phoneText}</a>
          </div>`
        : `
          <div style="font-size:12px;margin-top:2px;color:rgba(0,0,0,.70)">
            <b>Tel:</b> ${phoneText}
          </div>`;
    }

    const extra = d.extra ? `
      <div style="font-size:12px;margin-top:6px;color:rgba(0,0,0,.70)">
        <b>${escapeHtml(d.extra)}</b>
      </div>` : "";

    return `
      <div style="padding:10px 12px;border-radius:14px;background:#fff;border:1px solid rgba(0,0,0,.18);
                  box-shadow:0 6px 14px rgba(0,0,0,.10);min-width:310px;">
        <div style="display:flex;align-items:flex-start;gap:10px;">
          <div style="width:8px;height:64px;border-radius:999px;background:#E10600;"></div>
          <div style="flex:1;">
            <div style="font-weight:900;color:#0B0B0B;font-size:14px;line-height:1.1">${name}</div>
            ${cargo}
            ${area}
            ${email}
            ${phoneHtml}
            ${extra}
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

  async function init(){
    const payload = await fetchJson(dataUrl);

    elTitle.textContent = payload.title ?? "Organigrama";
    const nodes = payload.nodes ?? [];

    chart = new OrgChart()
      .container("#orgChart")
      .data(nodes)
      .nodeId(d => d.id)
      .parentNodeId(d => d.parentId)
      .nodeWidth(() => 320)
      .nodeHeight(() => 140)
      .childrenMargin(() => 45)
      .compactMarginBetween(() => 20)
      .compactMarginPair(() => 40)
      .nodeContent(nodeTemplate);

    chart.render();
    if (chart.fit) chart.fit();
  }

  init().catch(err => {
    console.error(err);
    elTitle.textContent = "Organigrama (error)";
  });
</script>

<?= $this->endSection() ?>