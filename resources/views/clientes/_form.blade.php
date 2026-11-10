@php
    // En «Nuevo cliente» no hay $cliente; en «Editar» trae los datos guardados.
    $c = $cliente ?? null;
@endphp

<p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
    Los campos marcados con <span class="text-red-600">*</span> son obligatorios.
</p>

<div class="mb-4">
    <x-input-label for="id_tipo_documento">
        Tipo de documento <span class="text-red-600">*</span>
    </x-input-label>
    <select id="id_tipo_documento" name="id_tipo_documento" required
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
        @unless ($c)
            <option value="">Selecciona un tipo</option>
        @endunless
        @foreach ($tiposDocumento as $tipo)
            <option value="{{ $tipo->id_tipo_documento }}"
                    @selected(old('id_tipo_documento', $c?->id_tipo_documento) == $tipo->id_tipo_documento)>
                {{ $tipo->nombre_tipo_documento }}
            </option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('id_tipo_documento')" class="mt-2" />
</div>

<div class="grid grid-cols-3 gap-4 mb-4">
    <div class="col-span-2">
        <x-input-label for="documento_identidad">
            Número de documento <span class="text-red-600">*</span>
        </x-input-label>
        <x-text-input id="documento_identidad" name="documento_identidad" type="text"
                      class="mt-1 block w-full"
                      value="{{ old('documento_identidad', $c?->documento_identidad) }}" required />
        <x-input-error :messages="$errors->get('documento_identidad')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="complemento" value="Complemento" />
        <x-text-input id="complemento" name="complemento" type="text"
                      class="mt-1 block w-full uppercase"
                      value="{{ old('complemento', $c?->complemento) }}" />
        <x-input-error :messages="$errors->get('complemento')" class="mt-2" />
    </div>
</div>

{{-- Verifica el NIT en el SIN (verde/rojo) y completa el nombre fijo de los códigos 99002 y 99003 --}}
<x-estado-nit :tipos-nit="$tiposDocumento->where('codigo_sin', 5)->pluck('id_tipo_documento')->values()"
              :url="route('clientes.verificar-nit')"
              :especiales="\App\Modules\Sin\Services\SinNitService::RAZON_SOCIAL_ESPECIAL" />

<div class="mb-4">
    <x-input-label for="razon_social">
        Razón social / Nombre <span class="text-red-600">*</span>
    </x-input-label>
    <x-text-input id="razon_social" name="razon_social" type="text"
                  class="mt-1 block w-full uppercase"
                  value="{{ old('razon_social', $c?->razon_social) }}" required />
    <x-input-error :messages="$errors->get('razon_social')" class="mt-2" />
</div>

<div class="mb-4">
    <x-input-label for="nombre_responsable" value="Responsable (opcional)" />
    <x-text-input id="nombre_responsable" name="nombre_responsable" type="text"
                  class="mt-1 block w-full uppercase"
                  value="{{ old('nombre_responsable', $c?->nombre_responsable) }}" />
    <x-input-error :messages="$errors->get('nombre_responsable')" class="mt-2" />
</div>

<x-lista-dinamica nombre="correos" etiqueta="Correos electrónicos (opcional)"
                  tipo="email"
                  :valores="old('correos', $c ? $c->correos->pluck('correo')->all() : [])"
                  texto-agregar="+ Agregar otro correo" />

<x-lista-dinamica nombre="telefonos" etiqueta="Teléfonos (opcional)"
                  tipo="text"
                  :valores="old('telefonos', $c ? $c->telefonos->pluck('telefono')->all() : [])"
                  texto-agregar="+ Agregar otro teléfono" />

<div class="mb-4">
    <x-input-label for="direccion" value="Dirección (opcional)" />
    <x-text-input id="direccion" name="direccion" type="text"
                  class="mt-1 block w-full"
                  value="{{ old('direccion', $c?->direccion) }}" />
    <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
</div>

{{-- Validación al escribir: teléfono (solo números), responsable (solo letras) y correo. El servidor vuelve a validar. --}}
@verbatim
<script>
    (function () {
        var form = document.currentScript.closest('form');
        if (!form) return;

        var mensajes = new Map();

        function esTelefono(el) { return /^telefonos/.test(el.name || ''); }
        function esCorreo(el) { return /^correos/.test(el.name || ''); }
        function esResponsable(el) { return el.id === 'nombre_responsable'; }
        function aplica(el) { return el.tagName === 'INPUT' && (esTelefono(el) || esCorreo(el) || esResponsable(el)); }

        // Quita lo que no corresponde mientras se escribe.
        function limpiar(el) {
            var antes = el.value, v = antes;

            if (esTelefono(el)) v = v.replace(/[^0-9 +()\-]/g, '').slice(0, 20);
            else if (esResponsable(el)) v = v.replace(/[^\p{L} .'\-]/gu, '').slice(0, 100);
            else if (esCorreo(el)) v = v.replace(/\s/g, '').slice(0, 100);

            if (v !== antes) el.value = v;
        }

        // Devuelve el texto del error, o '' si está bien (los campos vacíos son opcionales).
        function error(el) {
            var v = el.value.trim();
            if (v === '') return '';

            if (esTelefono(el)) {
                var digitos = v.replace(/\D/g, '').length;
                if (digitos < 7) return 'El teléfono debe tener al menos 7 dígitos.';
                if (digitos > 15) return 'El teléfono admite como máximo 15 dígitos.';
                if (!/^\+?[0-9 ()\-]+$/.test(v)) return 'Un teléfono solo admite números, espacios, + ( ) y guion.';
            } else if (esResponsable(el)) {
                if (v.length < 3) return 'El responsable debe tener al menos 3 caracteres.';
            } else if (esCorreo(el)) {
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Escribe un correo válido, por ejemplo nombre@empresa.com.';
            }
            return '';
        }

        function limpiarHuerfanos() {
            mensajes.forEach(function (p, el) {
                if (!el.isConnected) { p.remove(); mensajes.delete(el); }
            });
        }

        function mostrar(el, texto) {
            var p = mensajes.get(el);

            if (!p) {
                if (!texto) return;
                p = document.createElement('p');
                p.className = 'mt-1 text-sm text-red-600';
                p.setAttribute('role', 'alert');
                // En las listas dinámicas el mensaje va debajo de la fila, para no romper su diseño.
                (esResponsable(el) ? el : (el.parentElement || el)).insertAdjacentElement('afterend', p);
                mensajes.set(el, p);
            }

            p.textContent = texto;
            p.hidden = !texto;
            el.style.borderColor = texto ? '#dc2626' : '';
        }

        function revisar(el) {
            limpiarHuerfanos();
            var texto = error(el);
            mostrar(el, texto);
            return texto === '';
        }

        form.addEventListener('input', function (e) {
            if (!aplica(e.target)) return;
            limpiar(e.target);
            // Mientras escribe solo se actualiza el aviso si ya había uno.
            if (mensajes.has(e.target) && !mensajes.get(e.target).hidden) revisar(e.target);
        });

        form.addEventListener('focusout', function (e) {
            if (aplica(e.target)) revisar(e.target);
        });

        form.addEventListener('submit', function (e) {
            var primero = null;

            form.querySelectorAll('input').forEach(function (el) {
                if (aplica(el) && !revisar(el) && !primero) primero = el;
            });

            if (primero) {
                e.preventDefault();
                primero.focus();
            }
        });
    })();
</script>
@endverbatim
