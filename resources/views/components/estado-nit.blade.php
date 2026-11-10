@props(['tiposNit' => [], 'url', 'especiales' => []])

{{-- Aviso del estado del NIT en el SIN: verde si es válido, rojo si no lo reconoce. No impide guardar. --}}
<div class="mb-4 -mt-2"
     x-data="{
        tiposNit: @js($tiposNit),
        url: @js($url),
        especiales: @js($especiales),
        fijo: '',
        estado: '',
        mensaje: '',
        espera: null,
        ultimo: '',

        revisar() {
            const tipo = document.getElementById('id_tipo_documento')?.value;
            const doc = (document.getElementById('documento_identidad')?.value || '').trim();
            clearTimeout(this.espera);

            if (!this.tiposNit.map(String).includes(String(tipo)) || doc === '') {
                this.estado = '';
                this.mensaje = '';
                this.ultimo = '';
                this.fijo = '';
                return;
            }

            // Algunos códigos especiales exigen un nombre fijo: se completa solo.
            this.fijo = this.especiales[doc] || '';
            if (this.fijo) {
                const nombre = document.getElementById('razon_social');
                if (nombre) nombre.value = this.fijo;
            }

            const clave = tipo + '|' + doc;
            if (clave === this.ultimo) return;

            this.espera = setTimeout(() => this.consultar(doc, clave), 700);
        },

        async consultar(doc, clave) {
            this.ultimo = clave;
            this.estado = 'cargando';
            this.mensaje = 'Consultando el NIT en el SIN...';

            try {
                const r = await fetch(this.url + '?nit=' + encodeURIComponent(doc), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!r.ok) throw new Error('fallo');
                const d = await r.json();
                if (clave !== this.ultimo) return;

                // Si el SIN no responde, no se muestra nada.
                this.estado = ['ACTIVO', 'ESPECIAL', 'INVALIDO'].includes(d.estado) ? d.estado : '';
                this.mensaje = d.mensaje || '';
            } catch (e) {
                if (clave === this.ultimo) { this.estado = ''; this.mensaje = ''; }
            }
        },
     }"
     x-init="['id_tipo_documento', 'documento_identidad'].forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.addEventListener('input', () => revisar()); el.addEventListener('change', () => revisar()); }
             });
             revisar();">

 <p x-show="fijo" x-cloak class="text-sm text-gray-600 dark:text-gray-400">
        El SIN exige el nombre «<span x-text="fijo"></span>» para este código: se completó automáticamente.
    </p>

    <p x-show="estado === 'cargando'" x-cloak class="text-sm text-gray-500 dark:text-gray-400" x-text="mensaje"></p>

    <p x-show="estado === 'ACTIVO' || estado === 'ESPECIAL'" x-cloak
       class="text-sm font-medium text-green-700 dark:text-green-400">
        ✓ NIT válido en el SIN (<span x-text="mensaje"></span>).
    </p>

    <p x-show="estado === 'INVALIDO'" x-cloak class="text-sm font-medium text-red-600 dark:text-red-400">
        ✕ El SIN no reconoce este NIT (<span x-text="mensaje"></span>).
        Puedes guardar igual, pero la factura necesitará excepción de NIT.
    </p>
</div>
