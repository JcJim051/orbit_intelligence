@php
    $steps = [
        ['admin.postgis.index', '1', 'Preparar', 'Conexión y accesos', request()->routeIs('admin.postgis.*')],
        ['admin.spatial-imports.index', '2', 'Recibir', 'Carga desde QGIS', request()->routeIs('admin.spatial-imports.*')],
        ['admin.spatial-datasets.index', '3', 'Organizar', 'Campos y formularios', request()->routeIs('admin.spatial-datasets.*')],
        ['admin.geo-viewers.index', '4', 'Publicar', 'Visores y capas', request()->routeIs('admin.geo-viewers.*', 'admin.geo-layers.*')],
        ['geo-viewers.demo', '5', 'Comprobar', 'Portal ciudadano', request()->routeIs('geo-viewers.demo')],
    ];
@endphp

<nav class="sig-workflow" aria-label="Proceso de publicación geográfica">
    <div class="sig-workflow-head">
        <div><p>Ruta de trabajo SIG</p><span>Siga el proceso sin perder el contexto.</span></div>
        <button type="button" data-workflow-toggle aria-expanded="false">Ver pasos</button>
    </div>
    <ol data-workflow-steps>
        @foreach($steps as [$route, $number, $title, $description, $active])
            @if(!in_array($route, ['admin.postgis.index', 'admin.spatial-imports.index'], true) || auth()->user()->isAdmin())
                <li><a href="{{ route($route) }}" class="{{ $active ? 'is-active' : '' }}" @if($active) aria-current="step" @endif><span class="sig-step-number">{{ $number }}</span><span><strong>{{ $title }}</strong><small>{{ $description }}</small></span></a></li>
            @endif
        @endforeach
    </ol>
</nav>
