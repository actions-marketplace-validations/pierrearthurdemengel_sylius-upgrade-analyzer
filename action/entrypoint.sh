#!/bin/bash
set -e

# Script d'execution de l'action GitHub
# Installe l'outil et lance l'analyse sur le projet cible

PROJECT_PATH="${INPUT_PROJECT_PATH:-.}"
TARGET_VERSION="${INPUT_TARGET_VERSION:-2.2}"
FORMAT="${INPUT_FORMAT:-json}"
OUTPUT="${INPUT_OUTPUT:-sylius-upgrade-report.json}"

echo "Analyse du projet Sylius dans : ${PROJECT_PATH}"
echo "Version cible : ${TARGET_VERSION}"

cd "${PROJECT_PATH}"

# Installation de l'outil
composer require --dev pierre-arthur/sylius-upgrade-analyzer --no-interaction

# Lancement de l'analyse
vendor/bin/sylius-upgrade-analyzer analyse . \
    --format="${FORMAT}" \
    --output="${OUTPUT}" \
    --target-version="${TARGET_VERSION}" \
    --no-marketplace

echo "Analyse terminee. Rapport genere dans : ${OUTPUT}"
