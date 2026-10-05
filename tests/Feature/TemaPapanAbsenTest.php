<?php

test('tema papan absen memakai palet, font, dan radius desain A', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $vite = file_get_contents(base_path('vite.config.ts'));

    expect($css)
        ->toContain('--g-bg: #f4f1ea;')
        ->toContain('--g-blue: #24613a;')
        ->toContain('--g-bg: #121712;')
        ->toContain('--g-sky-c: #e3ebf6;')
        ->toContain("'Bricolage Grotesque', 'Public Sans'")
        ->toContain("'Public Sans'")
        ->toContain("'JetBrains Mono'")
        ->toContain('--radius-2xl: 10px;')
        ->not->toContain('Roboto')
        ->and($vite)
        ->toContain("google('Public Sans'")
        ->toContain("google('Bricolage Grotesque'")
        ->toContain("google('JetBrains Mono'")
        ->not->toContain('Roboto');
});
