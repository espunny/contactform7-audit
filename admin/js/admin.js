/**
 * JavaScript para panel de administración
 *
 * @package ContactForm7_Audit
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    // Inicializar datepickers
    initDatepickers();

    // Botón de verificación de integridad
    $("#cfa-verify-btn").on("click", verifyChainIntegrity);

    // Botón de exportación
    $("#cfa-export-btn").on("click", exportData);

    // Botón de ver detalles
    $(document).on("click", ".cfa-view-details", viewRecordDetails);

    // Botón de purgar base de datos
    $("#cfa-purge-btn").on("click", purgeDatabase);
  });

  /**
   * Inicializar selectores de fecha
   */
  function initDatepickers() {
    $(".cfa-datepicker").datepicker({
      dateFormat: "yy-mm-dd",
      maxDate: 0, // No permitir fechas futuras
      changeMonth: true,
      changeYear: true,
      yearRange: "-10:+0",
    });

    // Establecer fecha de fin como hoy
    $("#cfa-date-end").datepicker("setDate", new Date());

    // Establecer fecha de inicio como hace 30 días
    var thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    $("#cfa-date-start").datepicker("setDate", thirtyDaysAgo);
  }

  /**
   * Verificar integridad de la cadena de hash
   */
  function verifyChainIntegrity() {
    var $btn = $("#cfa-verify-btn");
    var $progress = $("#cfa-verify-progress");
    var $progressFill = $(".cfa-progress-fill");
    var $progressText = $(".cfa-progress-text");
    var $result = $("#cfa-verify-result");

    // Deshabilitar botón
    $btn.prop("disabled", true);

    // Mostrar barra de progreso
    $progress.show();
    $result.html("");

    // Variables para tracking
    var offset = 0;
    var totalRecords = 0;
    var verifiedRecords = 0;
    var chunkSize = cfaAjax.chunk_size;
    var isValid = true;

    // Función recursiva para verificar por chunks
    function verifyChunk() {
      $.ajax({
        url: cfaAjax.ajax_url,
        type: "POST",
        data: {
          action: "cfa_verify_chain",
          nonce: cfaAjax.nonce,
          offset: offset,
          limit: chunkSize,
        },
        success: function (response) {
          if (response.success) {
            var data = response.data;

            // Actualizar total en la primera iteración
            if (totalRecords === 0) {
              totalRecords = data.total_records;
            }

            verifiedRecords += data.verified_records;

            // Actualizar barra de progreso
            var percentage =
              totalRecords > 0
                ? Math.round((verifiedRecords / totalRecords) * 100)
                : 100;
            $progressFill.css("width", percentage + "%");
            $progressText.text(
              percentage +
                "% - " +
                verifiedRecords +
                " " +
                cfaAjax.strings.verified
            );

            // Verificar si la cadena sigue siendo válida
            if (!data.valid) {
              isValid = false;
              showVerificationResult(false, data);
              return;
            }

            // Continuar con el siguiente chunk
            if (verifiedRecords < totalRecords) {
              offset += chunkSize;
              verifyChunk();
            } else {
              // Verificación completada
              showVerificationResult(true, data);
            }
          } else {
            showError(response.data.message || cfaAjax.strings.error);
          }
        },
        error: function (xhr, status, error) {
          showError(cfaAjax.strings.error + " " + error);
        },
      });
    }

    // Iniciar verificación
    verifyChunk();

    /**
     * Mostrar resultado de la verificación
     */
    function showVerificationResult(valid, data) {
      $btn.prop("disabled", false);

      if (valid) {
        $result.html(
          '<div class="notice notice-success"><p><strong>' +
            cfaAjax.strings.valid_chain +
            "</strong><br>" +
            totalRecords +
            " " +
            cfaAjax.strings.verified +
            "</p></div>"
        );
      } else {
        $result.html(
          '<div class="notice notice-error"><p><strong>' +
            cfaAjax.strings.invalid_chain +
            "</strong><br>" +
            cfaAjax.strings.corrupted_at +
            " " +
            data.corrupted_record +
            "<br>" +
            cfaAjax.strings.error_details +
            " " +
            data.error_details +
            "</p></div>"
        );
      }

      // Ocultar barra de progreso después de 2 segundos
      setTimeout(function () {
        $progress.fadeOut();
      }, 2000);
    }

    /**
     * Mostrar error
     */
    function showError(message) {
      $btn.prop("disabled", false);
      $progress.hide();
      $result.html(
        '<div class="notice notice-error"><p>' +
          cfaAjax.strings.error +
          " " +
          message +
          "</p></div>"
      );
    }
  }

  /**
   * Exportar datos a JSON
   */
  function exportData() {
    var $btn = $("#cfa-export-btn");
    var $result = $("#cfa-export-result");

    // Obtener fechas
    var dateStart = $("#cfa-date-start").val();
    var dateEnd = $("#cfa-date-end").val();

    // Validar fechas
    if (!dateStart || !dateEnd) {
      $result.html(
        '<div class="notice notice-warning"><p>' +
          "Por favor selecciona un rango de fechas válido." +
          "</p></div>"
      );
      return;
    }

    // Deshabilitar botón
    $btn.prop("disabled", true);
    $result.html(
      '<div class="notice notice-info"><p>' +
        cfaAjax.strings.exporting +
        ' <span class="spinner is-active" style="float:none;"></span></p></div>'
    );

    $.ajax({
      url: cfaAjax.ajax_url,
      type: "POST",
      data: {
        action: "cfa_export_data",
        nonce: cfaAjax.nonce,
        date_start: dateStart,
        date_end: dateEnd,
      },
      success: function (response) {
        $btn.prop("disabled", false);

        if (response.success) {
          var data = response.data;

          // Crear blob con los datos JSON
          var jsonString = JSON.stringify(data.data, null, 2);
          var blob = new Blob([jsonString], { type: "application/json" });

          // Crear enlace de descarga
          var url = URL.createObjectURL(blob);
          var link = document.createElement("a");
          link.href = url;
          link.download = data.filename;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);

          // Mostrar mensaje de éxito
          $result.html(
            '<div class="notice notice-success"><p>' +
              cfaAjax.strings.export_complete +
              ": <strong>" +
              data.filename +
              "</strong>" +
              "<br>Total de registros: " +
              data.data.export_info.total_records +
              "</p></div>"
          );

          // Limpiar mensaje después de 5 segundos
          setTimeout(function () {
            $result.fadeOut(function () {
              $(this).html("").show();
            });
          }, 5000);
        } else {
          $result.html(
            '<div class="notice notice-error"><p>' +
              cfaAjax.strings.error +
              " " +
              response.data.message +
              "</p></div>"
          );
        }
      },
      error: function (xhr, status, error) {
        $btn.prop("disabled", false);
        $result.html(
          '<div class="notice notice-error"><p>' +
            cfaAjax.strings.error +
            " " +
            error +
            "</p></div>"
        );
      },
    });
  }

  /**
   * Ver detalles de un registro
   */
  function viewRecordDetails() {
    var recordId = $(this).data("record-id");

    $.ajax({
      url: cfaAjax.ajax_url,
      type: "POST",
      data: {
        action: "cfa_get_records",
        nonce: cfaAjax.nonce,
        record_id: recordId,
      },
      success: function (response) {
        if (response.success) {
          var record = response.data.record;
          showRecordModal(record);
        } else {
          alert(cfaAjax.strings.error + " " + response.data.message);
        }
      },
      error: function (xhr, status, error) {
        alert(cfaAjax.strings.error + " " + error);
      },
    });
  }

  /**
   * Mostrar modal con detalles del registro
   */
  function showRecordModal(record) {
    var modalHtml =
      '<div id="cfa-modal-overlay" class="cfa-modal-overlay">' +
      '<div class="cfa-modal">' +
      '<div class="cfa-modal-header">' +
      "<h2>Detalles del Registro #" +
      record.id +
      "</h2>" +
      '<button class="cfa-modal-close">&times;</button>' +
      "</div>" +
      '<div class="cfa-modal-body">' +
      '<div class="cfa-detail-section">' +
      "<h3>Información General</h3>" +
      '<table class="cfa-detail-table">' +
      "<tr><td><strong>ID:</strong></td><td>" +
      record.id +
      "</td></tr>" +
      "<tr><td><strong>Formulario:</strong></td><td>" +
      record.form_name +
      " (ID: " +
      record.form_id +
      ")</td></tr>" +
      "<tr><td><strong>Fecha:</strong></td><td>" +
      record.created_at +
      "</td></tr>" +
      "<tr><td><strong>IP Usuario:</strong></td><td>" +
      record.user_ip +
      "</td></tr>" +
      "<tr><td><strong>User Agent:</strong></td><td>" +
      record.user_agent +
      "</td></tr>" +
      "</table>" +
      "</div>" +
      '<div class="cfa-detail-section">' +
      "<h3>Datos del Formulario</h3>" +
      "<pre>" +
      JSON.stringify(record.submission_data, null, 2) +
      "</pre>" +
      "</div>" +
      '<div class="cfa-detail-section">' +
      "<h3>Datos del Navegador</h3>" +
      "<pre>" +
      JSON.stringify(record.browser_data, null, 2) +
      "</pre>" +
      "</div>" +
      '<div class="cfa-detail-section">' +
      "<h3>Hashes</h3>" +
      '<table class="cfa-detail-table">' +
      "<tr><td><strong>Hash Anterior:</strong></td><td><code>" +
      record.previous_hash +
      "</code></td></tr>" +
      "<tr><td><strong>Hash Actual:</strong></td><td><code>" +
      record.current_hash +
      "</code></td></tr>" +
      "</table>" +
      "</div>" +
      "</div>" +
      "</div>" +
      "</div>";

    // Agregar modal al DOM
    $("body").append(modalHtml);

    // Cerrar modal
    $(".cfa-modal-close, .cfa-modal-overlay").on("click", function (e) {
      if (e.target === this) {
        $("#cfa-modal-overlay").remove();
      }
    });
  }

  /**
   * Purgar base de datos
   */
  function purgeDatabase() {
    // Mostrar diálogo de confirmación crítico
    var confirmation = prompt(cfaAjax.strings.purge_confirm);

    if (confirmation === null) {
      // Usuario canceló
      alert(cfaAjax.strings.purge_cancelled);
      return;
    }

    if (confirmation !== "ELIMINAR") {
      alert(cfaAjax.strings.purge_invalid);
      return;
    }

    var $btn = $("#cfa-purge-btn");
    var $result = $("#cfa-purge-result");

    // Deshabilitar botón
    $btn.prop("disabled", true);
    $result.html(
      '<div class="notice notice-warning"><p>' +
        cfaAjax.strings.purging +
        ' <span class="spinner is-active" style="float:none;"></span></p></div>'
    );

    $.ajax({
      url: cfaAjax.ajax_url,
      type: "POST",
      data: {
        action: "cfa_purge_database",
        nonce: cfaAjax.nonce,
      },
      success: function (response) {
        $btn.prop("disabled", false);

        if (response.success) {
          $result.html(
            '<div class="notice notice-success"><p><strong>' +
              cfaAjax.strings.purge_complete +
              " " +
              response.data.records_deleted +
              " " +
              cfaAjax.strings.records +
              "</strong><br>" +
              "Se ha creado un nuevo hash génesis. La cadena ha sido reiniciada." +
              "</p></div>"
          );

          // Recargar la página después de 3 segundos
          setTimeout(function () {
            location.reload();
          }, 3000);
        } else {
          $result.html(
            '<div class="notice notice-error"><p>' +
              cfaAjax.strings.error +
              " " +
              response.data.message +
              "</p></div>"
          );
        }
      },
      error: function (xhr, status, error) {
        $btn.prop("disabled", false);
        $result.html(
          '<div class="notice notice-error"><p>' +
            cfaAjax.strings.error +
            " " +
            error +
            "</p></div>"
        );
      },
    });
  }
})(jQuery);
