<!DOCTYPE html>
{{-- Banco de pruebas de formularios. No extiende el layout ni
     escribe en la base: sirve para ver qué llega en la petición --}}
<html lang="es">
<head><meta charset="UTF-8"><title>Sandbox de formulario</title></head>
<body>

    <h1>Laboratorio de formularios</h1>

    {{-- url() arma la dirección completa. Con la ruta escrita como
         "/practica/enviar" daba 404 en XAMPP, porque el proyecto no
         está servido en la raíz del dominio --}}
    <form action="{{ url('/practica/enviar') }}" method="POST">
        @csrf

        <label for="cliente">Cliente</label>
        <input id="cliente" name="cliente_nombre" type="text"><br>

        <label for="monto">Monto</label>
        <input id="monto" name="monto" type="number" step="0.01"><br>

        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="Iniciada">Iniciada</option>
            <option value="Completada">Completada</option>
        </select><br>

        {{-- Campos agregados para probar los tipos email y checkbox:
             un checkbox sin marcar no se envía, no llega en cero --}}
        <label for="correo">Correo de contacto</label>
        <input id="correo" name="correo_contacto" type="email"><br>

        <input id="recurrente" name="es_recurrente" type="checkbox" value="1">
        <label for="recurrente">¿Es una transacción recurrente?</label><br>

        <button type="submit">Enviar (modo prueba)</button>
    </form>
</body>
</html>
