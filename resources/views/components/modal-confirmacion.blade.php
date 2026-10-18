@once
<style>
    .mc-capa { position: fixed; inset: 0; z-index: 70; display: flex; align-items: center; justify-content: center;
               padding: 1rem; background: rgba(17, 24, 39, .55); }
    .mc-caja { width: 100%; max-width: 26rem; border-radius: .75rem; background: #fff; color: #374151;
               padding: 1.25rem 1.5rem; box-shadow: 0 20px 40px rgba(0, 0, 0, .25); }
    .dark .mc-caja { background: #1f2937; color: #d1d5db; }
    .mc-titulo { font-size: 1.0625rem; font-weight: 600; color: #111827; }
    .dark .mc-titulo { color: #f3f4f6; }
    .mc-texto { margin-top: .5rem; font-size: .875rem; line-height: 1.4rem; }
    .mc-acciones { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1.25rem; }
    .mc-btn { padding: .5rem 1rem; border: 1px solid transparent; border-radius: .375rem;
              font-size: .875rem; font-weight: 500; cursor: pointer; }
    .mc-cancelar { background: #fff; border-color: #d1d5db; color: #374151; }
    .dark .mc-cancelar { background: #374151; border-color: #4b5563; color: #e5e7eb; }
    .mc-peligro { background: #dc2626; color: #fff; }
    .mc-peligro:hover { background: #b91c1c; }
    .mc-ok { background: #16a34a; color: #fff; }
    .mc-ok:hover { background: #15803d; }
</style>
@endonce

<div x-data="{
         abierto: false,
         form: null,
         d: { titulo: '', texto: '', boton: 'Confirmar', peligro: true },
         abrir(e) {
             this.form = e.detail.form;
             this.d = Object.assign({ titulo: '', texto: '', boton: 'Confirmar', peligro: true }, e.detail);
             this.abierto = true;
             this.$nextTick(() => this.$refs.cancelar.focus());
         },
         aceptar() {
             this.abierto = false;
             if (this.form) this.form.submit();
         }
     }"
     x-on:confirmar-accion.window="abrir($event)"
     x-on:keydown.escape.window="abierto = false">

    <div class="mc-capa" x-show="abierto" x-cloak x-transition.opacity.duration.150ms
         x-on:click.self="abierto = false" role="dialog" aria-modal="true">
        <div class="mc-caja">
            <h3 class="mc-titulo" x-text="d.titulo"></h3>
            <p class="mc-texto" x-text="d.texto"></p>

            <div class="mc-acciones">
                <button type="button" class="mc-btn mc-cancelar" x-ref="cancelar" x-on:click="abierto = false">Cancelar</button>
                <button type="button" class="mc-btn" :class="d.peligro ? 'mc-peligro' : 'mc-ok'"
                        x-on:click="aceptar()" x-text="d.boton"></button>
            </div>
        </div>
    </div>
</div>
