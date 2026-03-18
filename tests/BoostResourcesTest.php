<?php

it('ships a laravel boost guideline at the expected path', function () {
    $path = dirname(__DIR__).'/resources/boost/guidelines/core.blade.php';

    expect($path)->toBeFile();

    $contents = file_get_contents($path);

    expect($contents)->not->toBeFalse();

    $contents = (string) $contents;

    expect($contents)
        ->toContain('# Cord')
        ->toContain('withCompany()')
        ->toContain('schema()')
        ->toContain('fromStructured()')
        ->toContain('inspect()')
        ->toContain('rawXml()');
});

it('ships a laravel boost skill with required frontmatter', function () {
    $path = dirname(__DIR__).'/resources/boost/skills/cord-development/SKILL.md';

    expect($path)->toBeFile();

    $contents = file_get_contents($path);

    expect($contents)->not->toBeFalse();

    $contents = (string) $contents;

    expect(str_starts_with($contents, "---\nname: cord-development\n"))->toBeTrue()
        ->and((bool) preg_match('/^---\nname: cord-development\ndescription: ".+"\n---\n/s', $contents))->toBeTrue()
        ->and($contents)->toContain('## When to use this skill')
        ->toContain('describe()')
        ->toContain('schema()')
        ->toContain('fromStructured()')
        ->toContain('inspect()')
        ->toContain('rawXml()');
});