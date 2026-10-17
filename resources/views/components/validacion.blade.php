@once
<style>
    .val-error { border-color: #ef4444 !important; background-color: rgba(239, 68, 68, .06) !important; }
    .val-error:focus { border-color: #ef4444 !important; box-shadow: 0 0 0 2px rgba(239, 68, 68, .35) !important; }
    .val-ok { border-color: #22c55e !important; }
    .val-ok:focus { border-color: #22c55e !important; box-shadow: 0 0 0 2px rgba(34, 197, 94, .30) !important; }
    [data-reglas]:disabled { opacity: .5; cursor: not-allowed; }

    .val-msg { display: flex; flex: 1; align-items: flex-start; gap: .375rem; margin-top: .25rem;
               font-size: .8125rem; line-height: 1.15rem; color: #ef4444; }
    .val-msg[hidden] { display: none; }
    .val-msg svg { width: 1rem; height: 1rem; flex: none; margin-top: .05rem; }

    .val-pie { display: flex; align-items: flex-start; gap: .75rem; }
    .val-ayuda { font-size: .75rem; color: #6b7280; }
    .val-contador { margin-left: auto; margin-top: .25rem; font-size: .75rem; color: #9ca3af; white-space: nowrap; }
    .val-contador.val-alto { color: #d97706; }
    .val-contador.val-lleno { color: #ef4444; }

    .val-resumen { margin-bottom: 1rem; padding: .625rem .875rem; border: 1px solid #fecaca; border-radius: .375rem;
                   background: #fef2f2; color: #b91c1c; font-size: .875rem; }
    .val-resumen[hidden] { display: none; }
</style>

<script>
(function () {
    if (window.Validacion) { window.Validacion.iniciar(); return; }

    var ICONO = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>';

    var RE_RAZON = /^[\p{L}\p{N} .,&'"\-\/()#°ºª+]+$/u;
    var RE_PERSONA = /^[\p{L} .'\-]+$/u;
    var RE_CORREO = /^[A-Za-z0-9._%+\-]+\x40[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/;

    function codigoTipo(form) {
        var sel = form.querySelector('[name="id_tipo_documento"]');
        var op = sel && sel.selectedOptions[0];
        return op ? (op.dataset.codigo || '') : '';
    }

    function normalizar(valor, grupo) {
        return grupo === 'telefonos' ? valor.replace(/\D/g, '') : valor.trim().toLowerCase();
    }

    /* Cada regla devuelve true, un texto de error, o { suave: true, mensaje } si solo está incompleto. */
    var reglas = {
        min: function (v, n) {
            return v.trim().length >= +n || { suave: true, mensaje: 'Escribe al menos ' + n + ' caracteres.' };
        },
        max: function (v, n) {
            return v.length <= +n || 'Máximo ' + n + ' caracteres.';
        },
        razon: function (v) {
            return RE_RAZON.test(v.trim()) || 'Hay caracteres no permitidos. Usa letras, números y signos comunes.';
        },
        persona: function (v) {
            return RE_PERSONA.test(v.trim()) || 'Solo letras, espacios, punto, apóstrofo y guion.';
        },
        correo: function (v) {
            return RE_CORREO.test(v.trim()) || 'Escribe un correo válido, por ejemplo nombre\u0040dominio.com.';
        },
        telefono: function (v) {
            var t = v.trim();
            if (!/^\+?[0-9 ()\-]+$/.test(t)) return 'Solo números, espacios, + ( ) y guion.';
            var d = t.replace(/\D/g, '').length;
            if (d > 15) return 'El teléfono admite como máximo 15 dígitos.';
            if (d < 7) return { suave: true, mensaje: 'El teléfono debe tener al menos 7 dígitos.' };
            return true;
        },
        complemento: function (v) {
            return /^[0-9A-Za-z]{1,2}$/.test(v.trim()) || 'Hasta 2 letras o números, por ejemplo 1A.';
        },
        unico: function (v, _a, campo, form) {
            var g = campo.dataset.grupo;
            if (!g) return true;
            var clave = normalizar(v, g);
            if (clave === '') return true;
            var repetido = Array.prototype.some.call(form.querySelectorAll('[data-grupo="' + g + '"]'), function (o) {
                return o !== campo && !o.disabled && normalizar(o.value, g) === clave;
            });
            return !repetido || 'Este valor está repetido.';
        },
        documento: function (v, _a, campo, form) {
            var codigo = codigoTipo(form);
            var t = v.trim();
            if (codigo === '') return { suave: true, mensaje: 'Primero elige el tipo de documento.' };

            if (codigo === '1' || codigo === '5') {
                var min = codigo === '1' ? 5 : 7;
                var max = codigo === '1' ? 10 : 13;
                var nombre = codigo === '1' ? 'La cédula de identidad' : 'El NIT';
                if (!/^\d+$/.test(t)) return nombre + ' solo admite números.';
                if (t.length > max) return nombre + ' admite como máximo ' + max + ' dígitos.';
                if (t.length < min) return { suave: true, mensaje: nombre + ' debe tener de ' + min + ' a ' + max + ' dígitos.' };
                return true;
            }

            if (!/^[A-Za-z0-9.\-]+$/.test(t)) return 'Solo letras, números, punto y guion.';
            if (t.length < 3) return { suave: true, mensaje: 'Escribe al menos 3 caracteres.' };
            return true;
        }
    };

    function evaluar(campo, form) {
        if (campo.disabled) return { ok: true, vacio: true };

        var valor = campo.value || '';
        var lista = (campo.dataset.reglas || '').split('|').filter(Boolean).map(function (r) {
            var i = r.indexOf(':');
            return i < 0 ? [r, ''] : [r.slice(0, i), r.slice(i + 1)];
        });
        var exigido = lista.some(function (r) { return r[0] === 'requerido'; });

        if (valor.trim() === '') {
            if (!exigido) return { ok: true, vacio: true };
            return {
                ok: false, suave: true,
                mensaje: campo.tagName === 'SELECT' ? 'Selecciona una opción.' : 'Este campo es obligatorio.'
            };
        }

        for (var i = 0; i < lista.length; i++) {
            var regla = reglas[lista[i][0]];
            if (!regla) continue;
            var r = regla(valor, lista[i][1], campo, form);
            if (r !== true) {
                return typeof r === 'string'
                    ? { ok: false, mensaje: r, suave: false }
                    : { ok: false, mensaje: r.mensaje, suave: !!r.suave };
            }
        }
        return { ok: true };
    }

    function pintar(campo, res, tecleando) {
        campo.classList.remove('val-error', 'val-ok');
        var caja = campo.closest('[data-campo]');
        var msg = caja ? caja.querySelector('[data-mensaje]') : null;

        function ocultar() { if (msg) { msg.hidden = true; msg.textContent = ''; } }

        if (res.ok) {
            if (!res.vacio) campo.classList.add('val-ok');
            ocultar();
            return;
        }
        if (tecleando && res.suave && campo.dataset.tocado !== '1') { ocultar(); return; }

        campo.classList.add('val-error');
        if (msg) {
            msg.innerHTML = ICONO + '<span></span>';
            msg.lastChild.textContent = res.mensaje;
            msg.hidden = false;
        }
    }

    function revisar(campo, form, tecleando) {
        pintar(campo, evaluar(campo, form), tecleando);
        var g = campo.dataset.grupo;
        if (g) {
            form.querySelectorAll('[data-grupo="' + g + '"]').forEach(function (o) {
                if (o !== campo && o.dataset.tocado === '1') pintar(o, evaluar(o, form), false);
            });
        }
    }

    function contar(campo) {
        var caja = campo.closest('[data-campo]');
        var c = caja && caja.querySelector('[data-contador]');
        if (!c) return;
        var max = parseInt(campo.dataset.max || campo.getAttribute('maxlength') || '0', 10);
        var n = campo.value.length;
        c.textContent = n + '/' + max;
        c.classList.toggle('val-alto', max > 0 && n >= max * 0.9 && n < max);
        c.classList.toggle('val-lleno', max > 0 && n >= max);
    }

    function mayusculas(campo) {
        if (!campo.hasAttribute('data-mayusculas')) return;
        var a = campo.selectionStart, b = campo.selectionEnd, up = campo.value.toUpperCase();
        if (up !== campo.value) {
            campo.value = up;
            try { campo.setSelectionRange(a, b); } catch (e) {}
        }
    }

    function ajustarComplemento(form) {
        var comp = form.querySelector('[name="complemento"]');
        if (!comp || !form.querySelector('[name="id_tipo_documento"]')) return;

        var permitido = codigoTipo(form) === '1';
        comp.disabled = !permitido;
        comp.placeholder = permitido ? 'Ej: 1A' : 'No aplica';

        if (!permitido) {
            comp.value = '';
            comp.classList.remove('val-error', 'val-ok');
            var caja = comp.closest('[data-campo]');
            var msg = caja && caja.querySelector('[data-mensaje]');
            if (msg) { msg.hidden = true; msg.textContent = ''; }
        }
    }

    function revisarServidor(raiz) {
        var form = raiz.closest ? raiz.closest('form') : null;
        raiz.querySelectorAll('[data-error-servidor]').forEach(function (c) {
            var m = c.dataset.errorServidor;
            if (!m) return;
            c.dataset.tocado = '1';
            pintar(c, { ok: false, mensaje: m, suave: false }, false);
        });
    }

    function revalidarGrupo(form, grupo) {
        if (!form) return;
        form.querySelectorAll('[data-grupo="' + grupo + '"]').forEach(function (o) {
            if (o.dataset.tocado === '1') pintar(o, evaluar(o, form), false);
        });
    }

    function iniciar() {
        document.querySelectorAll('form[data-validar]').forEach(function (form) {
            if (form.dataset.validacionLista) return;
            form.dataset.validacionLista = '1';
            form.noValidate = true;

            function campos() { return form.querySelectorAll('[data-reglas]'); }
            function esCampo(el) { return el && el.matches && el.matches('[data-reglas]'); }

            form.addEventListener('input', function (e) {
                var c = e.target;
                if (!esCampo(c)) return;
                mayusculas(c);
                contar(c);
                revisar(c, form, true);
            });

            form.addEventListener('focusout', function (e) {
                var c = e.target;
                if (!esCampo(c) || c.disabled) return;
                c.dataset.tocado = '1';
                revisar(c, form, false);
            });

            form.addEventListener('change', function (e) {
                var c = e.target;
                if (!esCampo(c)) return;

                if (c.name === 'id_tipo_documento') {
                    ajustarComplemento(form);
                    ['documento_identidad', 'complemento'].forEach(function (n) {
                        var o = form.querySelector('[name="' + n + '"]');
                        if (o && o.dataset.tocado === '1') revisar(o, form, false);
                    });
                }
                if (c.tagName === 'SELECT') {
                    c.dataset.tocado = '1';
                    revisar(c, form, false);
                }
            });

            form.addEventListener('submit', function (e) {
                var malos = [];
                campos().forEach(function (c) {
                    if (c.disabled) return;
                    c.dataset.tocado = '1';
                    var r = evaluar(c, form);
                    pintar(c, r, false);
                    if (!r.ok) malos.push(c);
                });

                var resumen = form.querySelector('[data-resumen]');
                if (malos.length) {
                    e.preventDefault();
                    if (resumen) {
                        resumen.textContent = malos.length === 1
                            ? 'Revisa el campo marcado en rojo antes de guardar.'
                            : 'Revisa los ' + malos.length + ' campos marcados en rojo antes de guardar.';
                        resumen.hidden = false;
                    }
                    malos[0].focus({ preventScroll: true });
                    malos[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (resumen) {
                    resumen.hidden = true;
                }
            });

            ajustarComplemento(form);
            campos().forEach(contar);
            revisarServidor(form);
        });
    }

    window.Validacion = { iniciar: iniciar, revisarServidor: revisarServidor, revalidarGrupo: revalidarGrupo };

    iniciar();
    document.addEventListener('DOMContentLoaded', iniciar);
})();
</script>
@endonce
