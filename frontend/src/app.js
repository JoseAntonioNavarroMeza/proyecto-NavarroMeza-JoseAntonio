const form = document.getElementById('form-equipo');
const lista = document.getElementById('lista-equipos');
const sourceBadge = document.getElementById('source-badge');
const counterBadge = document.getElementById('counter-badge');
const mensajeDiv = document.getElementById('mensaje');
const inputBuscar = document.getElementById('input-buscar');
const btnBuscar = document.getElementById('btn-buscar');
const btnLimpiar = document.getElementById('btn-limpiar');

function mostrarMensaje(txt, tipo = 'success') {
    mensajeDiv.className = `alert alert-${tipo}`;
    mensajeDiv.textContent = txt;
    mensajeDiv.classList.remove('hidden');
    setTimeout(() => mensajeDiv.classList.add('hidden'), 4000);
}

async function cargarEquipos(busqueda = '') {
    try {
        const url = busqueda ? `/api/?buscar=${encodeURIComponent(busqueda)}` : '/api/';
        const res = await fetch(url);
        const data = await res.json();

        sourceBadge.textContent = data.source === 'redis_cache' ? '⚡ Caché (Redis)' : '🗄️ Base de Datos (MariaDB)';
        sourceBadge.style.color = data.source === 'redis_cache' ? '#d69e2e' : '#3182ce';
        counterBadge.textContent = data.consultas_totales;

        lista.innerHTML = '';
        data.equipos.forEach(eq => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${eq.id}</td>
                <td><strong>${eq.nombre}</strong></td>
                <td>${eq.numero_serie}</td>
                <td>${eq.laboratorio}</td>
                <td>
                    <select onchange="cambiarEstado(${eq.id}, this.value)">
                        <option value="Operativo" ${eq.estado === 'Operativo' ? 'selected' : ''}>Operativo</option>
                        <option value="En Mantenimiento" ${eq.estado === 'En Mantenimiento' ? 'selected' : ''}>En Mantenimiento</option>
                        <option value="Baja" ${eq.estado === 'Baja' ? 'selected' : ''}>Baja</option>
                    </select>
                </td>
                <td>
                    <button class="btn btn-danger" onclick="eliminarEquipo(${eq.id})">Eliminar</button>
                </td>
            `;
            lista.appendChild(tr);
        });
    } catch (e) {
        mostrarMensaje('Error al obtener equipos del backend', 'danger');
    }
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        nombre: document.getElementById('nombre').value,
        numero_serie: document.getElementById('numero_serie').value,
        laboratorio: document.getElementById('laboratorio').value,
        estado: document.getElementById('estado').value
    };

    try {
        const res = await fetch('/api/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (res.ok) {
            mostrarMensaje('Equipo registrado y correo simulado enviado a Mailpit');
            form.reset();
            cargarEquipos();
        } else {
            const err = await res.json();
            mostrarMensaje(err.error || 'Error al guardar', 'danger');
        }
    } catch (err) {
        mostrarMensaje('Error de conexión con el backend', 'danger');
    }
});

async function cambiarEstado(id, nuevoEstado) {
    try {
        const res = await fetch(`/api/?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ estado: nuevoEstado })
        });
        if (res.ok) {
            mostrarMensaje('Estado actualizado e invalidada la caché de Redis');
            cargarEquipos();
        }
    } catch (e) {
        mostrarMensaje('Error al actualizar estado', 'danger');
    }
}

async function eliminarEquipo(id) {
    if (!confirm(`¿Estás seguro de eliminar el equipo con ID ${id}?`)) return;
    try {
        const res = await fetch(`/api/?id=${id}`, { method: 'DELETE' });
        if (res.ok) {
            mostrarMensaje('Equipo eliminado correctamente');
            cargarEquipos();
        }
    } catch (e) {
        mostrarMensaje('Error al eliminar el equipo', 'danger');
    }
}

btnBuscar.addEventListener('click', () => cargarEquipos(inputBuscar.value.trim()));
btnLimpiar.addEventListener('click', () => { inputBuscar.value = ''; cargarEquipos(); });

// Carga inicial
cargarEquipos();