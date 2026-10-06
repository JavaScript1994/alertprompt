<?php

declare(strict_types=1);

use App\Services\TemplateRenderer;

beforeEach(function () {
    $this->renderer = new TemplateRenderer;
});

it('extracts unique variable names from a body', function () {
    $body = 'Hola {{nombre}}, tu pedido {{ pedido }} está listo. Gracias {{nombre}}!';

    expect($this->renderer->extractVariables($body))->toBe(['nombre', 'pedido']);
});

it('returns an empty list when there are no variables', function () {
    expect($this->renderer->extractVariables('Hola, esto es un mensaje fijo.'))->toBe([]);
});

it('renders a body replacing known variables', function () {
    $body = 'Hola {{nombre}}, tu pedido {{pedido}} está listo.';

    $rendered = $this->renderer->render($body, ['nombre' => 'Carlos', 'pedido' => '#123']);

    expect($rendered)->toBe('Hola Carlos, tu pedido #123 está listo.');
});

it('leaves unresolved variables untouched when rendering', function () {
    $body = 'Hola {{nombre}}, tu pedido {{pedido}} está listo.';

    $rendered = $this->renderer->render($body, ['nombre' => 'Carlos']);

    expect($rendered)->toBe('Hola Carlos, tu pedido {{pedido}} está listo.');
});

it('reports missing variables not present in the given data', function () {
    $body = 'Hola {{nombre}}, tu pedido {{pedido}} está listo.';

    expect($this->renderer->missingVariables($body, ['nombre' => 'Carlos']))->toBe(['pedido']);
});
