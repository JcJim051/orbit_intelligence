<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#064e3b">
    <title>Gestión de Bancos | SIID 2.0</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <a href="#contenido-principal" class="fixed left-3 top-3 z-50 -translate-y-24 rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white focus:translate-y-0">Saltar al contenido</a>

    <div class="bg-emerald-950 text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-2 text-xs sm:px-6 lg:px-8">
            <span>Gobernación del Meta</span>
            <span class="font-medium tracking-wide">SISTEMA INTEGRADO DE INFORMACIÓN DEPARTAMENTAL · SIID 2.0</span>
        </div>
    </div>

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <span class="grid size-12 place-items-center rounded-2xl bg-emerald-700 text-sm font-black tracking-tight text-white shadow-sm">GB</span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">SIID 2.0</p>
                    <p class="text-lg font-bold tracking-tight text-slate-950 sm:text-xl">Gestión de Bancos</p>
                </div>
            </div>
            <div class="hidden items-center gap-2 text-sm text-slate-600 sm:flex">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                Radicación y seguimiento documental
            </div>
        </div>
    </header>

    <main id="contenido-principal" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-9 lg:px-8">
        <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-emerald-900 to-teal-800 text-white shadow-lg">
            <div class="grid gap-8 px-6 py-8 sm:px-9 sm:py-10 lg:grid-cols-[1fr_auto] lg:items-end">
                <div class="max-w-3xl">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-emerald-50">
                        <span class="size-2 rounded-full bg-lime-300"></span>
                        Gestión clara, seguimiento en un solo lugar
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Gestión de Bancos</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-emerald-50/90 sm:text-base">
                        Radique documentos, consulte su avance y mantenga la trazabilidad de cada respuesta sin depender del correo.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 p-4 text-sm backdrop-blur-sm">
                    <p class="font-semibold">¿Primera vez aquí?</p>
                    <p class="mt-1 max-w-xs leading-5 text-emerald-50/80">Envíe sus documentos y conserve el número de seguimiento para consultar el trámite.</p>
                </div>
            </div>
        </section>

        <div class="mt-5 flex flex-wrap items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
            <span class="rounded-md bg-amber-200 px-2 py-1 text-[11px] font-extrabold uppercase tracking-wide">Vista de demostración</span>
            <span>Los datos son ficticios. Este prototipo no carga archivos ni guarda cambios.</span>
        </div>

        <div class="mt-6 border-b border-slate-200">
            <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Vistas de Gestión de Bancos" role="tablist">
                <button type="button" class="whitespace-nowrap rounded-t-xl border-b-2 border-emerald-700 bg-white px-4 py-3 text-sm font-bold text-emerald-800" id="tab-radicar" role="tab" aria-selected="true" aria-controls="vista-radicar" data-screen-button="vista-radicar">
                    Radicar documentos
                </button>
                <button type="button" class="whitespace-nowrap rounded-t-xl border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-slate-500 hover:text-slate-800" id="tab-seguimiento" role="tab" aria-selected="false" aria-controls="vista-seguimiento" data-screen-button="vista-seguimiento" tabindex="-1">
                    Consultar trámite
                </button>
                <button type="button" class="whitespace-nowrap rounded-t-xl border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-slate-500 hover:text-slate-800" id="tab-gestion" role="tab" aria-selected="false" aria-controls="vista-gestion" data-screen-button="vista-gestion" tabindex="-1">
                    Bandeja de gestión
                </button>
            </nav>
        </div>

        <section id="vista-radicar" class="pt-6" role="tabpanel" aria-labelledby="tab-radicar" data-screen>
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(18rem,.8fr)]">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                    <div class="mb-6">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Remisión de documentos</p>
                        <h2 class="mt-2 text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">Radicar una solicitud</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Complete los datos de contacto, elija la dependencia y adjunte los documentos en PDF.</p>
                    </div>

                    <form data-demo-submit class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-semibold text-slate-700">
                                Nombre del remitente
                                <input type="text" name="nombre" autocomplete="name" placeholder="Nombre y apellidos" required class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm font-normal text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10">
                            </label>
                            <label class="block text-sm font-semibold text-slate-700">
                                Correo electrónico
                                <input type="email" name="correo" autocomplete="email" placeholder="nombre@entidad.gov.co" required class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm font-normal text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10">
                            </label>
                        </div>

                        <label class="block text-sm font-semibold text-slate-700">
                            Secretaría o dependencia destinataria
                            <select name="dependencia" required class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm font-normal text-slate-700 shadow-sm outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10">
                                <option value="">Seleccione una dependencia</option>
                                <option>Secretaría de Planeación</option>
                                <option>Secretaría de Hacienda</option>
                                <option>Secretaría de Educación</option>
                                <option>Secretaría de Infraestructura</option>
                                <option>Secretaría de Salud</option>
                            </select>
                        </label>

                        <label class="block text-sm font-semibold text-slate-700">
                            Asunto o descripción breve
                            <textarea name="descripcion" rows="3" placeholder="Describa brevemente los documentos que remite" class="mt-2 block w-full resize-y rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm font-normal text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10"></textarea>
                        </label>

                        <div>
                            <p class="text-sm font-semibold text-slate-700">Documentos PDF</p>
                            <label class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center transition hover:border-emerald-500 hover:bg-emerald-50/50 focus-within:ring-4 focus-within:ring-emerald-600/10">
                                <span class="mb-3 grid size-11 place-items-center rounded-xl bg-emerald-100 text-emerald-800" aria-hidden="true">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M4 15.5v3A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5v-3"/></svg>
                                </span>
                                <span class="text-sm font-bold text-slate-800">Seleccione o arrastre sus archivos</span>
                                <span class="mt-1 text-xs text-slate-500">Puede adjuntar varios PDF · límite por definir</span>
                                <input class="sr-only" type="file" name="documentos[]" accept="application/pdf,.pdf" multiple data-file-input>
                            </label>
                            <ul class="mt-3 hidden space-y-2" aria-live="polite" data-file-list></ul>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="max-w-md text-xs leading-5 text-slate-500">El sistema enviaría el radicado y el seguimiento al correo registrado.</p>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-emerald-700/20">
                                Vista previa de radicación
                                <span aria-hidden="true">→</span>
                            </button>
                        </div>
                        <p class="hidden rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status" data-submit-feedback></p>
                    </form>
                </div>

                <aside class="space-y-4">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="font-bold text-slate-950">¿Qué pasa después?</h2>
                        <ol class="mt-5 space-y-5">
                            <li class="flex gap-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs font-extrabold text-emerald-800">1</span>
                                <div><p class="text-sm font-bold text-slate-800">Reciba el radicado</p><p class="mt-1 text-xs leading-5 text-slate-500">SIID asignará el número y generará el documento con sello, fecha y hora.</p></div>
                            </li>
                            <li class="flex gap-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs font-extrabold text-emerald-800">2</span>
                                <div><p class="text-sm font-bold text-slate-800">Consulte el avance</p><p class="mt-1 text-xs leading-5 text-slate-500">Use el número de seguimiento y verifique su correo para consultar el estado.</p></div>
                            </li>
                            <li class="flex gap-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs font-extrabold text-emerald-800">3</span>
                                <div><p class="text-sm font-bold text-slate-800">Responda si se requiere</p><p class="mt-1 text-xs leading-5 text-slate-500">Si se devuelve, verá el motivo y podrá cargar una nueva versión.</p></div>
                            </li>
                        </ol>
                    </section>
                    <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                        <p class="text-sm font-bold text-emerald-950">Importante</p>
                        <p class="mt-2 text-sm leading-6 text-emerald-900">Conserve el número de seguimiento. Se necesitará para consultar el trámite y cargar una corrección.</p>
                    </section>
                </aside>
            </div>
        </section>

        <section id="vista-seguimiento" class="hidden pt-6" role="tabpanel" aria-labelledby="tab-seguimiento" data-screen>
            <div class="mx-auto max-w-3xl">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Consulta segura</p>
                    <h2 class="mt-2 text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">Consulte su trámite</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Ingrese el número de seguimiento y el correo asociado. En el sistema final se enviará un código de verificación a ese correo.</p>
                    <form class="mt-6 grid gap-4 sm:grid-cols-[1fr_1fr_auto]" data-tracking-form>
                        <label class="block text-sm font-semibold text-slate-700">
                            Número de seguimiento
                            <input type="text" name="seguimiento" placeholder="Ej. GEB-2026-001284" required class="mt-2 block w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm font-normal uppercase outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10">
                        </label>
                        <label class="block text-sm font-semibold text-slate-700">
                            Correo electrónico
                            <input type="email" name="correo" placeholder="Correo usado al radicar" required class="mt-2 block w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm font-normal outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10">
                        </label>
                        <button type="submit" class="self-end rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800 sm:mb-0.5">Consultar</button>
                    </form>
                    <p class="mt-4 hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert" data-tracking-error></p>
                </div>

                <article class="mt-5 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-tracking-result>
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 p-5 sm:p-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Radicado de demostración</p>
                            <h3 class="mt-1 text-xl font-extrabold tracking-tight text-slate-950">GEB-2026-001284</h3>
                            <p class="mt-1 text-sm text-slate-600">Secretaría de Planeación · recibido el 18 nov 2026, 9:42 a. m.</p>
                        </div>
                        <span class="rounded-full bg-rose-100 px-3 py-1.5 text-xs font-bold text-rose-800">Devuelta</span>
                    </div>
                    <div class="grid gap-6 p-5 sm:p-6 md:grid-cols-[1fr_1.2fr]">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Documentos</h4>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600">
                                <li class="flex items-center gap-2"><span class="text-rose-600">PDF</span> Banco_proyectos.pdf</li>
                                <li class="flex items-center gap-2"><span class="text-rose-600">PDF</span> Anexo_tecnico.pdf</li>
                            </ul>
                            <button type="button" class="mt-4 rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Cargar nueva versión · vista previa</button>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Motivo de devolución</h4>
                            <p class="mt-2 rounded-xl bg-rose-50 p-3 text-sm leading-6 text-rose-900">Se solicita actualizar el anexo técnico y volver a cargar el documento completo.</p>
                            <ol class="mt-4 space-y-3 border-l-2 border-slate-200 pl-4 text-xs text-slate-600">
                                <li><strong class="text-slate-800">18 nov · Recibido</strong><span class="block mt-0.5">La solicitud fue radicada.</span></li>
                                <li><strong class="text-rose-800">20 nov · Devuelta</strong><span class="block mt-0.5">Se registró un motivo de devolución.</span></li>
                            </ol>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section id="vista-gestion" class="hidden pt-6" role="tabpanel" aria-labelledby="tab-gestion" data-screen>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Espacio del equipo interno</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">Bandeja de gestión</h2>
                    <p class="mt-1 text-sm text-slate-600">Trámites de las dependencias asignadas · datos de ejemplo</p>
                </div>
                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">
                    <span class="grid size-7 place-items-center rounded-full bg-emerald-100 font-bold text-emerald-800">MC</span>
                    María Camila · Gestora
                </span>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Por revisar</p><p class="mt-2 text-3xl font-extrabold text-slate-950">08</p><p class="mt-1 text-xs text-slate-500">Nuevos documentos recibidos</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">En proceso</p><p class="mt-2 text-3xl font-extrabold text-sky-800">12</p><p class="mt-1 text-xs text-slate-500">Trámites en gestión</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Devueltos</p><p class="mt-2 text-3xl font-extrabold text-rose-700">03</p><p class="mt-1 text-xs text-slate-500">Esperando nueva versión</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Finalizados</p><p class="mt-2 text-3xl font-extrabold text-emerald-800">24</p><p class="mt-1 text-xs text-slate-500">Gestión del módulo completada</p>
                </article>
            </div>

            <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(19rem,.8fr)]">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="bandeja-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                        <div><h3 id="bandeja-heading" class="font-bold text-slate-950">Documentos recibidos</h3><p class="mt-1 text-xs text-slate-500" data-case-count>4 trámites de ejemplo</p></div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                            <label class="sr-only" for="filtro-busqueda">Buscar en la bandeja</label>
                            <input id="filtro-busqueda" type="search" placeholder="Buscar radicado o dependencia" data-case-search class="min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-emerald-600 sm:w-56">
                            <label class="sr-only" for="filtro-estado">Filtrar por estado</label>
                            <select id="filtro-estado" data-case-filter class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-emerald-600">
                                <option value="">Todos los estados</option>
                                <option value="en-proceso">En proceso</option>
                                <option value="devuelta">Devuelta</option>
                                <option value="finalizada">Finalizada</option>
                            </select>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100" data-case-list>
                        <article class="flex flex-col gap-3 p-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between" data-case-row data-status="en-proceso" data-search="geb-2026-001291 secretaria de planeacion banco de proyectos andrea torres">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2"><p class="font-bold text-slate-900">GEB-2026-001291</p><span class="rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-bold text-sky-800">En proceso</span></div>
                                <p class="mt-1 truncate text-sm text-slate-700">Banco de proyectos · Secretaría de Planeación</p><p class="mt-1 text-xs text-slate-500">Andrea Torres · Hoy, 9:18 a. m. · 2 PDF</p>
                            </div>
                            <button type="button" data-case-open data-reference="GEB-2026-001291" data-dependencia="Secretaría de Planeación" data-remitente="Andrea Torres" data-fecha="Hoy, 9:18 a. m." data-estado="En proceso" data-files="Banco_proyectos.pdf · Anexo_tecnico.pdf" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:border-emerald-500 hover:text-emerald-800">Ver trámite</button>
                        </article>
                        <article class="flex flex-col gap-3 p-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between" data-case-row data-status="devuelta" data-search="geb-2026-001284 secretaria de hacienda plan de inversion camilo rojas">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2"><p class="font-bold text-slate-900">GEB-2026-001284</p><span class="rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-bold text-rose-800">Devuelta</span></div>
                                <p class="mt-1 truncate text-sm text-slate-700">Plan de inversión · Secretaría de Hacienda</p><p class="mt-1 text-xs text-slate-500">Camilo Rojas · Ayer, 3:46 p. m. · 1 PDF</p>
                            </div>
                            <button type="button" data-case-open data-reference="GEB-2026-001284" data-dependencia="Secretaría de Hacienda" data-remitente="Camilo Rojas" data-fecha="Ayer, 3:46 p. m." data-estado="Devuelta" data-files="Plan_inversion.pdf" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:border-emerald-500 hover:text-emerald-800">Ver trámite</button>
                        </article>
                        <article class="flex flex-col gap-3 p-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between" data-case-row data-status="en-proceso" data-search="geb-2026-001279 secretaria de educacion dotacion escolar luz marina">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2"><p class="font-bold text-slate-900">GEB-2026-001279</p><span class="rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-bold text-sky-800">En proceso</span></div>
                                <p class="mt-1 truncate text-sm text-slate-700">Dotación escolar · Secretaría de Educación</p><p class="mt-1 text-xs text-slate-500">Luz Marina Díaz · 17 nov 2026 · 3 PDF</p>
                            </div>
                            <button type="button" data-case-open data-reference="GEB-2026-001279" data-dependencia="Secretaría de Educación" data-remitente="Luz Marina Díaz" data-fecha="17 nov 2026" data-estado="En proceso" data-files="Dotacion.pdf · Listado.pdf · Cotizacion.pdf" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:border-emerald-500 hover:text-emerald-800">Ver trámite</button>
                        </article>
                        <article class="flex flex-col gap-3 p-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between" data-case-row data-status="finalizada" data-search="geb-2026-001263 secretaria de infraestructura mantenimiento vial juan david">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2"><p class="font-bold text-slate-900">GEB-2026-001263</p><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800">Finalizada</span></div>
                                <p class="mt-1 truncate text-sm text-slate-700">Mantenimiento vial · Secretaría de Infraestructura</p><p class="mt-1 text-xs text-slate-500">Juan David Pérez · 16 nov 2026 · 2 PDF</p>
                            </div>
                            <button type="button" data-case-open data-reference="GEB-2026-001263" data-dependencia="Secretaría de Infraestructura" data-remitente="Juan David Pérez" data-fecha="16 nov 2026" data-estado="Finalizada" data-files="Mantenimiento.pdf · Presupuesto.pdf" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:border-emerald-500 hover:text-emerald-800">Ver trámite</button>
                        </article>
                        <p class="hidden p-6 text-center text-sm text-slate-500" data-case-empty>No hay trámites que coincidan con la búsqueda.</p>
                    </div>
                </section>

                <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="detalle-heading">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Detalle seleccionado</p><h3 id="detalle-heading" class="mt-1 font-bold text-slate-950" data-detail-reference>GEB-2026-001291</h3></div>
                        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-bold text-sky-800" data-detail-status>En proceso</span>
                    </div>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div><dt class="text-xs font-semibold text-slate-500">Dependencia</dt><dd class="mt-1 font-medium text-slate-800" data-detail-dependency>Secretaría de Planeación</dd></div>
                        <div><dt class="text-xs font-semibold text-slate-500">Remitente</dt><dd class="mt-1 font-medium text-slate-800" data-detail-sender>Andrea Torres</dd></div>
                        <div><dt class="text-xs font-semibold text-slate-500">Recibido</dt><dd class="mt-1 font-medium text-slate-800" data-detail-date>Hoy, 9:18 a. m.</dd></div>
                    </dl>
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Archivos adjuntos</h4>
                        <p class="mt-2 rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-700" data-detail-files>Banco_proyectos.pdf · Anexo_tecnico.pdf</p>
                        <button type="button" class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-700 hover:border-emerald-500 hover:text-emerald-800">Descargar documentos · vista previa</button>
                    </div>
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Acciones del gestor</h4>
                        <label class="mt-3 block text-xs font-semibold text-slate-700">Actualizar estado
                            <select class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal text-slate-700">
                                <option>En proceso</option><option>Devuelta</option><option>Finalizada</option>
                            </select>
                        </label>
                        <label class="mt-3 block text-xs font-semibold text-slate-700">Motivo / nota para el remitente
                            <textarea rows="3" placeholder="Obligatorio al devolver el trámite" class="mt-1.5 block w-full resize-y rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10"></textarea>
                        </label>
                        <button type="button" class="mt-3 w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-800">Guardar cambio · vista previa</button>
                        <p class="mt-2 text-center text-[11px] leading-4 text-slate-500">La finalización cierra la gestión en este módulo; no reemplaza el proceso manual posterior.</p>
                    </div>
                </aside>
            </div>
        </section>

        <footer class="mt-10 border-t border-slate-200 py-5 text-center text-xs text-slate-500">
            Gobernación del Meta · SIID 2.0 · Gestión de Bancos
        </footer>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const screenButtons = [...document.querySelectorAll('[data-screen-button]')];
            const screens = [...document.querySelectorAll('[data-screen]')];

            screenButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    screenButtons.forEach((item) => {
                        const selected = item === button;
                        item.setAttribute('aria-selected', String(selected));
                        item.tabIndex = selected ? 0 : -1;
                        item.classList.toggle('border-emerald-700', selected);
                        item.classList.toggle('bg-white', selected);
                        item.classList.toggle('font-bold', selected);
                        item.classList.toggle('text-emerald-800', selected);
                        item.classList.toggle('border-transparent', ! selected);
                        item.classList.toggle('font-semibold', ! selected);
                        item.classList.toggle('text-slate-500', ! selected);
                    });

                    screens.forEach((screen) => {
                        screen.classList.toggle('hidden', screen.id !== button.dataset.screenButton);
                    });
                });
            });

            const fileInput = document.querySelector('[data-file-input]');
            const fileList = document.querySelector('[data-file-list]');
            fileInput?.addEventListener('change', () => {
                fileList.replaceChildren();

                [...fileInput.files].forEach((file) => {
                    const item = document.createElement('li');
                    item.className = 'rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700';
                    item.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
                    fileList.append(item);
                });

                fileList.classList.toggle('hidden', fileInput.files.length === 0);
            });

            document.querySelector('[data-demo-submit]')?.addEventListener('submit', (event) => {
                event.preventDefault();
                const feedback = document.querySelector('[data-submit-feedback]');
                feedback.textContent = 'Prototipo: la solicitud no fue enviada y no se generó un radicado. En el módulo operativo, SIID asignará el número al confirmar la recepción.';
                feedback.classList.remove('hidden');
            });

            document.querySelector('[data-tracking-form]')?.addEventListener('submit', (event) => {
                event.preventDefault();
                const formData = new FormData(event.currentTarget);
                const isSampleRequest = formData.get('seguimiento')?.toString().trim().toUpperCase() === 'GEB-2026-001284'
                    && formData.get('correo')?.toString().trim().toLowerCase() === 'remitente@ejemplo.gov.co';
                const result = document.querySelector('[data-tracking-result]');
                const error = document.querySelector('[data-tracking-error]');

                result.classList.toggle('hidden', ! isSampleRequest);
                error.classList.toggle('hidden', isSampleRequest);

                if (! isSampleRequest) {
                    error.textContent = 'En esta demostración solo está disponible el caso de ejemplo: GEB-2026-001284 y remitente@ejemplo.gov.co.';
                }
            });

            const caseRows = [...document.querySelectorAll('[data-case-row]')];
            const caseSearch = document.querySelector('[data-case-search]');
            const caseFilter = document.querySelector('[data-case-filter]');
            const caseEmpty = document.querySelector('[data-case-empty]');
            const caseCount = document.querySelector('[data-case-count]');

            const filterCases = () => {
                const query = caseSearch.value.trim().toLowerCase();
                const status = caseFilter.value;
                let visibleCases = 0;

                caseRows.forEach((row) => {
                    const matchesSearch = row.dataset.search.includes(query);
                    const matchesStatus = ! status || row.dataset.status === status;
                    const isVisible = matchesSearch && matchesStatus;
                    row.classList.toggle('hidden', ! isVisible);
                    visibleCases += isVisible ? 1 : 0;
                });

                caseEmpty.classList.toggle('hidden', visibleCases > 0);
                caseCount.textContent = `${visibleCases} ${visibleCases === 1 ? 'trámite de ejemplo' : 'trámites de ejemplo'}`;
            };

            caseSearch?.addEventListener('input', filterCases);
            caseFilter?.addEventListener('change', filterCases);

            document.querySelectorAll('[data-case-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.querySelector('[data-detail-reference]').textContent = button.dataset.reference;
                    document.querySelector('[data-detail-dependency]').textContent = button.dataset.dependencia;
                    document.querySelector('[data-detail-sender]').textContent = button.dataset.remitente;
                    document.querySelector('[data-detail-date]').textContent = button.dataset.fecha;
                    document.querySelector('[data-detail-files]').textContent = button.dataset.files;

                    const status = document.querySelector('[data-detail-status]');
                    status.textContent = button.dataset.estado;
                    status.className = 'rounded-full px-2.5 py-1 text-[11px] font-bold';
                    status.classList.add(
                        button.dataset.estado === 'Devuelta' ? 'bg-rose-100' : button.dataset.estado === 'Finalizada' ? 'bg-emerald-100' : 'bg-sky-100',
                        button.dataset.estado === 'Devuelta' ? 'text-rose-800' : button.dataset.estado === 'Finalizada' ? 'text-emerald-800' : 'text-sky-800',
                    );
                });
            });
        });
    </script>
</body>
</html>
