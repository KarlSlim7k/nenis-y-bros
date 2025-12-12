#!/bin/bash
set -e

# Deshabilitar todos los MPMs y habilitar solo mpm_prefork
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# Ejecutar el comando original de Apache
exec apache2-foreground
