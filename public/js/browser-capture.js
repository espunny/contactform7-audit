/**
 * Script para capturar datos del navegador
 *
 * @package ContactForm7_Audit
 */

(function ($) {
  "use strict";

  /**
   * Capturar datos del navegador al cargar la página
   */
  function captureBrowserData() {
    // Capturar todas las cookies del dominio
    var cookies = document.cookie || "no-cookies";

    // Capturar resolución de pantalla
    var screenWidth = screen.width || 0;
    var screenHeight = screen.height || 0;

    return {
      cookies: cookies,
      screenWidth: screenWidth,
      screenHeight: screenHeight,
    };
  }

  /**
   * Inyectar campos ocultos en formularios Contact Form 7
   */
  function injectHiddenFields() {
    // Buscar todos los formularios de Contact Form 7
    var forms = document.querySelectorAll(".wpcf7-form");

    if (forms.length === 0) {
      console.log("CFA: No se encontraron formularios CF7");
      return;
    }

    console.log("CFA: Encontrados " + forms.length + " formulario(s) CF7");

    // Obtener datos del navegador
    var browserData = captureBrowserData();
    console.log("CFA: Datos capturados:", browserData);

    forms.forEach(function (form) {
      // Verificar si ya se inyectaron los campos
      if (form.querySelector('input[name="_cfa_cookies"]')) {
        console.log("CFA: Campos ya inyectados en este formulario");
        return;
      }

      // Crear campo para cookies
      var cookiesField = document.createElement("input");
      cookiesField.type = "hidden";
      cookiesField.name = "_cfa_cookies";
      cookiesField.value = browserData.cookies;
      cookiesField.className = "wpcf7-form-control wpcf7-hidden";
      form.appendChild(cookiesField);

      // Crear campo para ancho de pantalla
      var widthField = document.createElement("input");
      widthField.type = "hidden";
      widthField.name = "_cfa_screen_width";
      widthField.value = browserData.screenWidth;
      widthField.className = "wpcf7-form-control wpcf7-hidden";
      form.appendChild(widthField);

      // Crear campo para alto de pantalla
      var heightField = document.createElement("input");
      heightField.type = "hidden";
      heightField.name = "_cfa_screen_height";
      heightField.value = browserData.screenHeight;
      heightField.className = "wpcf7-form-control wpcf7-hidden";
      form.appendChild(heightField);

      console.log("CFA: Campos ocultos inyectados exitosamente");
    });
  }

  /**
   * Actualizar campos antes de enviar
   */
  function updateFieldsBeforeSubmit(event) {
    var form = event.target;
    var browserData = captureBrowserData();

    // Actualizar valores por si cambiaron
    var cookiesField = form.querySelector('input[name="_cfa_cookies"]');
    if (cookiesField) {
      cookiesField.value = browserData.cookies;
    }

    var widthField = form.querySelector('input[name="_cfa_screen_width"]');
    if (widthField) {
      widthField.value = browserData.screenWidth;
    }

    var heightField = form.querySelector('input[name="_cfa_screen_height"]');
    if (heightField) {
      heightField.value = browserData.screenHeight;
    }
  }

  /**
   * Inicializar cuando el DOM esté listo
   */
  $(document).ready(function () {
    // Inyectar campos ocultos
    injectHiddenFields();

    // También escuchar evento cuando Contact Form 7 carga dinámicamente formularios
    $(document).on("wpcf7:init", function () {
      injectHiddenFields();
    });

    // Actualizar campos antes de enviar
    $(document).on("submit", ".wpcf7-form", updateFieldsBeforeSubmit);
  });

  /**
   * Re-inyectar campos después de envío (por si el formulario se reinicia)
   */
  document.addEventListener("wpcf7mailsent", function () {
    setTimeout(function () {
      injectHiddenFields();
    }, 100);
  });

  /**
   * Re-inyectar en caso de error
   */
  document.addEventListener("wpcf7invalid", function () {
    setTimeout(function () {
      injectHiddenFields();
    }, 100);
  });

  document.addEventListener("wpcf7spam", function () {
    setTimeout(function () {
      injectHiddenFields();
    }, 100);
  });

  document.addEventListener("wpcf7mailfailed", function () {
    setTimeout(function () {
      injectHiddenFields();
    }, 100);
  });
})(jQuery);
