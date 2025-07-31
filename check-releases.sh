#!/bin/bash

# Script pour vérifier l'état des releases Laravel Chronotrace
# Usage: ./check-releases.sh

set -e

# Couleurs pour les messages
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_message() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

print_message $BLUE "🔍 État des releases Laravel Chronotrace"
print_message $BLUE "═══════════════════════════════════════"

# Vérifier les tags locaux
print_message $YELLOW "📍 Tags locaux :"
if git tag -l | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | tail -5; then
    echo ""
else
    echo "   Aucun tag de version trouvé"
fi

# Vérifier les releases GitHub (si gh CLI est disponible)
if command -v gh &> /dev/null && gh auth status &> /dev/null; then
    print_message $YELLOW "🐙 Releases GitHub :"
    gh release list --limit 5
    echo ""
    
    print_message $YELLOW "🔄 Workflows récents :"
    gh run list --workflow=release.yml --limit 3
    echo ""
else
    print_message $YELLOW "⚠️  GitHub CLI non disponible ou non connecté"
fi

# Informations sur la dernière version
LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "Aucun tag")
print_message $YELLOW "🏷️  Dernière version : $LAST_TAG"

# Vérifier s'il y a des commits depuis la dernière release
if [ "$LAST_TAG" != "Aucun tag" ]; then
    COMMITS_SINCE=$(git rev-list $LAST_TAG..HEAD --count)
    if [ $COMMITS_SINCE -gt 0 ]; then
        print_message $YELLOW "📝 $COMMITS_SINCE commits depuis la dernière release"
        print_message $BLUE "💡 Changements depuis $LAST_TAG :"
        git log $LAST_TAG..HEAD --oneline --max-count=5
    else
        print_message $GREEN "✅ Aucun nouveau commit depuis la dernière release"
    fi
else
    print_message $YELLOW "📝 Aucune release précédente trouvée"
fi

print_message $BLUE "═══════════════════════════════════════"
print_message $GREEN "🎯 Pour créer une release : ./release.sh <version> [notes]"
print_message $GREEN "🌐 Packagist : https://packagist.org/packages/grazulex/laravel-chronotrace"
