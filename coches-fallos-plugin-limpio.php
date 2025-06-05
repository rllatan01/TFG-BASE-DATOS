<?php
/*
Plugin Name: Coches Fallos Manager
Description: Plugin para insertar y visualizar fallos de vehículos (marca, modelo, año, descripción, solución).
Version: 1.1
Author: Tu Nombre
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1) Enqueue jQuery en el front-end
 */
add_action( 'wp_enqueue_scripts', 'cfm_enqueue_scripts' );
function cfm_enqueue_scripts() {
    wp_enqueue_script( 'jquery' );
}

// --- Configuración de la conexión externa ---
function cfm_conectar() {
    $host   = 'localhost';
    $user   = 'coches';
    $pass   = 'Admin123';
    $dbname = 'coches';
    $conn = new mysqli( $host, $user, $pass, $dbname );
    if ( $conn->connect_error ) {
        return false;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * 2) Shortcode de inserción de fallos
 */
add_shortcode( 'insertar_fallo_coches', 'cfm_insert_form_shortcode' );
function cfm_insert_form_shortcode() {
    $conn = cfm_conectar();
    if ( ! \$conn ) return '<p><strong>Error:</strong> no se pudo conectar a la base de datos.</p>';

    // Marcas y años
    \$marcas = \$conn->query("SELECT id, nombre FROM marcas ORDER BY nombre");
    \$anios  = \$conn->query("SELECT año FROM años_fabricacion ORDER BY año DESC");

    <?php ob_start(); ?>
    <form id="cfm_insert_form">
      <label>Marca:</label><br>
      <select id="cfm_marca" name="marca" required>
        <option value="">--Selecciona Marca--</option>
        <?php while ( $m = $marcas->fetch_assoc() ): ?>
          <option value="<?php echo esc_attr($m['id']); ?>">
            <?php echo esc_html($m['nombre']); ?>
          </option>
        <?php endwhile; ?>
      </select><br><br>

      <label>Modelo:</label><br>
      <select id="cfm_modelo" name="modelo" disabled required>
        <option value="">--Selecciona Modelo--</option>
      </select><br><br>

      <label>Año:</label><br>
      <select id="cfm_anio" name="anio" required>
        <option value="">--Selecciona Año--</option>
        <?php while ( $y = $anios->fetch_assoc() ): ?>
          <option value="<?php echo esc_attr($y['año']); ?>">
            <?php echo esc_html($y['año']); ?>
          </option>
        <?php endwhile; ?>
      </select><br><br>

      <label>Descripción del fallo:</label><br>
      <textarea id="cfm_fallo" name="fallo" rows="4" required></textarea><br><br>

      <label>Solución:</label><br>
      <textarea id="cfm_solucion" name="solucion" rows="4" required></textarea><br><br>

      <button type="submit">Enviar fallo</button>
    </form>

    <div id="cfm_success_msg" style="display:none; color: green; font-weight: bold;">
      ¡Fallo insertado correctamente!
    </div>

    <script>
    jQuery(document).ready(function($) {
      $('#cfm_insert_form').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: $(this).serialize() + '&action=cfm_insert_fallo',
          success: function(response) {
            if (response === 'success') {
              $('#cfm_insert_form').hide();
              $('#cfm_success_msg').show();
            } else {
              alert('Error al insertar el fallo.');
            }
          }
        });
      });
    });
    </script>
    <?php return ob_get_clean(); ?>

}

// AJAX: carga de modelos
add_action( 'wp_ajax_cfm_cargar_modelos', 'cfm_cargar_modelos' );
add_action( 'wp_ajax_nopriv_cfm_cargar_modelos', 'cfm_cargar_modelos' );
function cfm_cargar_modelos() {
    if ( ! isset($_POST['marca_id']) ) wp_die();
    $mid = intval($_POST['marca_id']);
    $conn = cfm_conectar();
    if ( ! $conn ) {
      echo '<option value="">Error de conexión</option>';
      wp_die();
    }
    $res = $conn->query("SELECT id, nombre FROM modelos WHERE marca_id = $mid ORDER BY nombre");
    if ( $res && $res->num_rows ) {
        echo '<option value="">--Selecciona Modelo--</option>';
        while ( $r = $res->fetch_assoc() ) {
            echo '<option value="'.esc_attr($r['id']).'">'.esc_html($r['nombre']).'</option>';
        }
    } else {
        echo '<option value="">No hay modelos disponibles</option>';
    }
    $conn->close();
    wp_die();
}

// AJAX: insertar fallo
add_action( 'wp_ajax_cfm_insertar_fallo', 'cfm_insertar_fallo' );
add_action( 'wp_ajax_nopriv_cfm_insertar_fallo', 'cfm_insertar_fallo' );
function cfm_insertar_fallo() {
    foreach ( ['marca_id','modelo_id','anio','descripcion'] as $f ) {
        if ( ! isset($_POST[$f]) ) wp_die('Error: faltan datos');
    }
    $mid   = intval($_POST['marca_id']);
    $moid  = intval($_POST['modelo_id']);
    $anio  = intval($_POST['anio']);
    $desc  = trim($_POST['descripcion']);
    $conn  = cfm_conectar();
    if ( ! $conn ) wp_die('Error de conexión');
    $stmt = $conn->prepare(
      "INSERT INTO fallos (marca_id, modelo_id, anio, descripcion, solucion)
       VALUES (?,?,?,?, '')"
    );
    $stmt->bind_param('iiis', $mid, $moid, $anio, $desc);
    if ( $stmt->execute() ) {
        echo '<p style="color:green;">Fallo registrado correctamente.</p>';
    } else {
        echo '<p style="color:red;">Error al guardar el fallo.</p>';
    }
    $stmt->close();
    $conn->close();
    wp_die();
}

/**
 * 3) Shortcode de visualización de fallos
 */
add_shortcode( 'ver_fallos_coches', 'cfm_view_fallos_shortcode' );
function cfm_view_fallos_shortcode() {
    $conn = cfm_conectar();
    if ( ! $conn ) return '<p><strong>Error:</strong> no se pudo conectar a la base de datos.</p>';

    $marcas = $conn->query("SELECT id, nombre FROM marcas ORDER BY nombre");
    $anios  = $conn->query("SELECT año FROM años_fabricacion ORDER BY año DESC");

    ob_start(); ?>
    <form id="cfm_view_form" method="post">
      <label>Marca:</label>
      <select id="view_marca" name="marca" required>
        <option value="">--Marca--</option>
        <?php while ( $m = $marcas->fetch_assoc() ): ?>
          <option value="<?php echo esc_attr($m['id']); ?>">
            <?php echo esc_html($m['nombre']); ?>
          </option>
        <?php endwhile; ?>
      </select>

      <label>Modelo:</label>
      <select id="view_modelo" name="modelo" disabled required>
        <option value="">--Modelo--</option>
      </select>

      <label>Año:</label>
      <!-- Corregido: name="anio" -->
      <select id="view_anio" name="anio" required>
        <option value="">--Año--</option>
        <?php while ( $y = $anios->fetch_assoc() ): ?>
          <option value="<?php echo esc_attr($y['año']); ?>">
            <?php echo esc_html($y['año']); ?>
          </option>
        <?php endwhile; ?>
      </select>

      <button type="submit" name="cfm_view_submit">Ver fallos</button>
    </form>

    <div id="cfm_view_results">
    <?php
    if ( isset($_POST['cfm_view_submit']) ) {
        $mid  = intval($_POST['marca']);
        $moid = intval($_POST['modelo']);
        $anio = intval($_POST['anio']);
        $sql  = "SELECT f.fecha, f.descripcion, f.solucion
                   FROM fallos f
                  WHERE f.marca_id=$mid
                    AND f.modelo_id=$moid
                    AND f.anio=$anio
                  ORDER BY f.fecha DESC";
        $res = $conn->query($sql);
        if ( $res && $res->num_rows ) {
            echo '<table><tr><th>Fecha</th><th>Descripción</th><th>Solución</th></tr>';
            while ( $r = $res->fetch_assoc() ) {
                echo '<tr><td>'.esc_html($r['fecha']).'</td>'
                   .    '<td>'.esc_html($r['descripcion']).'</td>'
                   .    '<td>'.esc_html($r['solucion']).'</td></tr>';
            }
            echo '</table>';
        } else {
            echo '<p>No se encontraron fallos.</p>';
        }
    }
    ?>
    </div>

    <script>
    jQuery(function($){
      // Cargar modelos también en la vista
      $('#view_marca').on('change', function(){
        var mid = $(this).val();
        $('#view_modelo')
          .prop('disabled', true)
          .html('<option>Cargando…</option>');
        if (!mid) {
          $('#view_modelo')
            .html('<option value="">--Modelo--</option>');
          return;
        }
        $.post('<?php echo admin_url("admin-ajax.php"); ?>', {
          action:   'cfm_cargar_modelos',
          marca_id: mid
        }, function(data){
          $('#view_modelo')
            .html(data)
            .prop('disabled', false);
        });
      });
    });
    </script>
    <?php
    $conn->close();
    
    


    <div id="cfm_success_msg" style="display:none; color: green; font-weight: bold;">
      ¡Fallo insertado correctamente!
    </div>

    <script>
    jQuery(document).ready(function($) {
      $('#cfm_insert_form').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: $(this).serialize() + '&action=cfm_insert_fallo',
          success: function(response) {
            if (response === 'success') {
              $('#cfm_insert_form').hide();
              $('#cfm_success_msg').show();
            } else {
              alert('Error al insertar el fallo.');
            }
          }
        });
      });
    });
    </script>

return ob_get_clean();
}

add_action('wp_ajax_cfm_insert_fallo', 'cfm_procesar_fallo');
add_action('wp_ajax_nopriv_cfm_insert_fallo', 'cfm_procesar_fallo');

function cfm_procesar_fallo() {
    $conn = cfm_conectar();
    if ( ! $conn ) {
        echo 'error';
        wp_die();
    }

    $marca  = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $anio   = $_POST['anio'];
    $fallo  = $_POST['fallo'];
    $soluc  = $_POST['solucion'];

    $stmt = $conn->prepare("INSERT INTO fallos (marca_id, modelo_id, año, descripcion, solucion) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $marca, $modelo, $anio, $fallo, $soluc);

    if ($stmt->execute()) {
        echo 'success';
    } else {
        echo 'error';
    }

    $stmt->close();
    $conn->close();
    wp_die();
}


add_action('wp_head', function() {
    echo '<script type="text/javascript">var ajaxurl = "' . admin_url('admin-ajax.php') . '";</script>';
});
