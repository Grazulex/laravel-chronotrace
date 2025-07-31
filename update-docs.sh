#!/bin/bash

# Script pour créer une demande de mise à jour de documentation et d'exemples
# Usage: ./update-docs.sh [type] [description]

set -e

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Fonction pour afficher un message coloré
print_message() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Fonction pour afficher l'aide
show_help() {
    print_message $BLUE "📚 Script de mise à jour de documentation Laravel Chronotrace"
    echo
    print_message $YELLOW "Usage:"
    echo "  $0 [type] [description]"
    echo
    print_message $YELLOW "Types disponibles:"
    echo "  docs      - Mise à jour de la documentation"
    echo "  examples  - Mise à jour des exemples"
    echo "  readme    - Mise à jour du README"
    echo "  all       - Mise à jour complète (docs + exemples + README)"
    echo
    print_message $YELLOW "Exemples:"
    echo "  $0 docs \"Ajouter documentation pour les nouvelles méthodes d'export\""
    echo "  $0 examples \"Mettre à jour les exemples avec les traits\""
    echo "  $0 readme \"Synchroniser le README avec les dernières fonctionnalités\""
    echo "  $0 all \"Mise à jour complète après ajout des fonctionnalités d'export\""
    echo
    print_message $PURPLE "💡 Le script créera automatiquement un ticket GitHub"
}

# Vérifier les arguments
if [ $# -eq 0 ] || [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
    show_help
    exit 0
fi

UPDATE_TYPE=$1
DESCRIPTION=${2:-""}

# Valider le type de mise à jour
case $UPDATE_TYPE in
    docs|examples|readme|all)
        ;;
    *)
        print_message $RED "❌ Type de mise à jour invalide: $UPDATE_TYPE"
        echo
        show_help
        exit 1
        ;;
esac

# Vérifier que gh CLI est installé
if ! command -v gh &> /dev/null; then
    print_message $RED "❌ GitHub CLI (gh) n'est pas installé"
    print_message $YELLOW "💡 Installez-le avec: sudo apt install gh (Ubuntu) ou brew install gh (macOS)"
    exit 1
fi

# Vérifier que l'utilisateur est connecté à GitHub
if ! gh auth status &> /dev/null; then
    print_message $RED "❌ Vous n'êtes pas connecté à GitHub"
    print_message $YELLOW "💡 Connectez-vous avec: gh auth login"
    exit 1
fi

# Obtenir des informations sur le repository
REPO_INFO=$(gh repo view --json name,owner)
REPO_NAME=$(echo $REPO_INFO | jq -r '.name')
REPO_OWNER=$(echo $REPO_INFO | jq -r '.owner.login')

print_message $BLUE "📝 Création d'une demande de mise à jour pour $REPO_OWNER/$REPO_NAME..."

# Si aucune description n'est fournie, demander à l'utilisateur
if [ -z "$DESCRIPTION" ]; then
    print_message $YELLOW "💭 Veuillez fournir une description pour la mise à jour:"
    read -r DESCRIPTION
    
    if [ -z "$DESCRIPTION" ]; then
        print_message $RED "❌ Description requise"
        exit 1
    fi
fi

# Définir le titre et le contenu selon le type
case $UPDATE_TYPE in
    docs)
        TITLE="📚 Mise à jour de la documentation"
        LABELS="scope:docs"
        ;;
    examples)
        TITLE="💡 Mise à jour des exemples"
        LABELS="scope:docs"
        ;;
    readme)
        TITLE="📖 Mise à jour du README"
        LABELS="scope:docs"
        ;;
    all)
        TITLE="🔄 Mise à jour complète documentation et exemples"
        LABELS="scope:docs"
        ;;
esac

# Créer le contenu de l'issue
ISSUE_BODY="## 🎯 Objectif

$DESCRIPTION

## 📋 Tâches à effectuer

"

case $UPDATE_TYPE in
    docs)
        ISSUE_BODY+="- [ ] Relire le code source pour identifier les nouvelles fonctionnalités
- [ ] Mettre à jour la documentation dans \`docs/\`
- [ ] Vérifier que tous les fichiers de documentation sont cohérents
- [ ] Ajouter des exemples dans la documentation si nécessaire"
        ;;
    examples)
        ISSUE_BODY+="- [ ] Relire le code source pour identifier les nouvelles fonctionnalités
- [ ] Mettre à jour les exemples dans \`examples/\`
- [ ] Créer de nouveaux exemples si nécessaire
- [ ] Vérifier que tous les exemples fonctionnent correctement
- [ ] Mettre à jour le README des exemples"
        ;;
    readme)
        ISSUE_BODY+="- [ ] Relire le code source pour identifier les nouvelles fonctionnalités
- [ ] Mettre à jour le README principal
- [ ] Synchroniser les badges et liens
- [ ] Mettre à jour les exemples d'utilisation dans le README
- [ ] Vérifier la cohérence avec la documentation"
        ;;
    all)
        ISSUE_BODY+="- [ ] Relire le code source pour identifier les nouvelles fonctionnalités
- [ ] Mettre à jour la documentation dans \`docs/\`
- [ ] Mettre à jour les exemples dans \`examples/\`
- [ ] Mettre à jour le README principal
- [ ] Créer de nouveaux exemples si nécessaire
- [ ] Vérifier la cohérence entre documentation, exemples et README
- [ ] Tester tous les exemples"
        ;;
esac

ISSUE_BODY+="

## 🔍 Contexte technique

Veuillez examiner les fichiers suivants pour comprendre les changements récents :
- \`src/\` - Code source principal
- \`tests/\` - Tests (pour comprendre l'utilisation)
- \`composer.json\` - Dépendances et configuration

## 📁 Fichiers à mettre à jour

"

case $UPDATE_TYPE in
    docs)
        ISSUE_BODY+="- \`docs/\` - Tous les fichiers de documentation
- \`docs/README.md\` - Index de la documentation"
        ;;
    examples)
        ISSUE_BODY+="- \`examples/\` - Tous les fichiers d'exemples
- \`examples/README.md\` - Documentation des exemples"
        ;;
    readme)
        ISSUE_BODY+="- \`README.md\` - Documentation principale du projet"
        ;;
    all)
        ISSUE_BODY+="- \`README.md\` - Documentation principale
- \`docs/\` - Toute la documentation
- \`examples/\` - Tous les exemples
- \`examples/README.md\` - Documentation des exemples"
        ;;
esac

ISSUE_BODY+="

## ✅ Critères d'acceptation

- [ ] La documentation reflète fidèlement le code actuel
- [ ] Les exemples sont fonctionnels et à jour
- [ ] Le style et la cohérence sont respectés
- [ ] Aucun lien brisé ou référence obsolète
- [ ] En dehors des changements de documentation ou exemples, aucune modification de code/test n'est nécessaire

---

**🤖 Cette issue a été créée automatiquement via le script \`update-docs.sh\`**"

# Créer l'issue GitHub
print_message $BLUE "🎫 Création de l'issue GitHub..."

ISSUE_URL=$(gh issue create \
    --title "$TITLE" \
    --body "$ISSUE_BODY" \
    --label "$LABELS" \
    --assignee "@me")

if [ $? -eq 0 ]; then
    print_message $GREEN "✅ Issue créée avec succès !"
    print_message $BLUE "🔗 URL: $ISSUE_URL"
    print_message $GREEN "👥 Assignée automatiquement à vous"
    
    # Extraire le numéro de l'issue de l'URL
    ISSUE_NUMBER=$(echo $ISSUE_URL | grep -o '[0-9]*$')
    
    # Proposer d'ouvrir l'issue dans le navigateur
    echo -e "${BLUE}🌐 Voulez-vous ouvrir l'issue dans le navigateur ? ${NC}(y/N)"
    read -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        gh issue view $ISSUE_NUMBER --web
    fi
    
else
    print_message $RED "❌ Erreur lors de la création de l'issue"
    exit 1
fi

print_message $GREEN "🎉 Demande de mise à jour créée avec succès !"
print_message $PURPLE "💭 N'oubliez pas de mentionner les changements spécifiques dans l'issue si nécessaire"
