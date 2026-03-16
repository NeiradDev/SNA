document.addEventListener("DOMContentLoaded",()=>{
/* =========================
NORMALIZAR TELEFONO
========================= */
function normalizarTelefono(numero){

let tel = numero.replace(/\D/g,"")

if(tel.startsWith("0")){
tel = tel.substring(1)
}

if(!tel.startsWith("593")){
tel = "593"+tel
}

return tel

}
/* =========================
WHATSAPP INDIVIDUAL
========================= */

document.querySelectorAll(".btn-wsp").forEach(btn=>{

btn.addEventListener("click",()=>{

const nombre = btn.dataset.nombre
const telefono = normalizarTelefono(btn.dataset.telefono)

const mensaje =
`Hola ${nombre}, recuerda completar tu reporte semanal en el sistema.`

const url =
`https://wa.me/${telefono}?text=${encodeURIComponent(mensaje)}`

window.open(url,"_blank")

})

})
/* =========================
EXPORTAR EXCEL (CSV)
========================= */

document.getElementById("btnExportarExcel")
?.addEventListener("click",()=>{

const filas = document.querySelectorAll("#tablaPendientes tbody tr")

let csv = "Nombres,Apellidos,Telefono\n"

filas.forEach(fila=>{

/* solo exportar visibles */

if(fila.style.display==="none") return

const nombres = fila.children[0].innerText.trim()
const apellidos = fila.children[1].innerText.trim()
const telefono = fila.querySelector(".telefono").innerText.trim()

csv += `"${nombres}","${apellidos}","${telefono}"\n`

})

const blob = new Blob([csv], {type:"text/csv;charset=utf-8;"})

const url = URL.createObjectURL(blob)

const link = document.createElement("a")

const fecha = new Date().toISOString().slice(0,10)

link.href = url
link.download = `pendientes_reporte_${fecha}.csv`

link.click()

/* liberar memoria */
URL.revokeObjectURL(url)

})

/* =========================
BUSCADOR
========================= */

const buscador = document.getElementById("buscadorUsuarios")

buscador?.addEventListener("keyup",function(){

const texto = this.value.toLowerCase()

document.querySelectorAll("#tablaPendientes tbody tr")
.forEach(fila=>{

fila.style.display =
fila.innerText.toLowerCase().includes(texto)
? ""
: "none"

})

})

})
