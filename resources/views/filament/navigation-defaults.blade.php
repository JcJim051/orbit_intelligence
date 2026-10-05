@php
    $user = auth()->user();
    $navigationVersion = 'siid-management-v2-'.$user?->getKey().'-'.($user?->role?->value ?? 'guest');
    $collapsedGroups = ['Actas y compromisos', 'Inversión pública'];

    if ($user?->canAccessSpatialGovernance()) {
        $collapsedGroups[] = 'Inteligencia geográfica';
    }

    if ($user?->canManageDashboards()) {
        $collapsedGroups[] = 'Dashboards';
    }

    if ($user?->canAccessManagementGoals()) {
        $collapsedGroups[] = 'Estructura plan PDD';
        $collapsedGroups[] = 'Seguimiento a metas';
    }

    if ($user?->canAccessPlatformAdministration()) {
        $collapsedGroups[] = 'Administración';
    }
@endphp

<script>
    (() => {
        const navigationVersion = @js($navigationVersion);

        if (localStorage.getItem('siidNavigationVersion') === navigationVersion) {
            return;
        }

        localStorage.setItem('collapsedGroups', JSON.stringify(@js($collapsedGroups)));
        localStorage.setItem('siidNavigationVersion', navigationVersion);
    })();
</script>
