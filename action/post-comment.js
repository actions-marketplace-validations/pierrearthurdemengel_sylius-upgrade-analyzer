// Script Node.js pour poster un commentaire de resume sur une PR GitHub
// Lit le rapport JSON et genere un commentaire formate en Markdown

const fs = require('fs');
const { execSync } = require('child_process');

async function main() {
    const reportFile = process.env.REPORT_FILE || 'sylius-upgrade-report.json';

    if (!fs.existsSync(reportFile)) {
        console.error(`Rapport introuvable : ${reportFile}`);
        process.exit(1);
    }

    const report = JSON.parse(fs.readFileSync(reportFile, 'utf-8'));
    const summary = report.summary;

    // Construction du commentaire Markdown
    let comment = `## Sylius Upgrade Analyzer\n\n`;
    comment += `| Metrique | Valeur |\n`;
    comment += `|---|---|\n`;
    comment += `| Complexite | **${summary.complexity.toUpperCase()}** |\n`;
    comment += `| Heures estimees | ${summary.total_hours}h |\n`;
    comment += `| Issues BREAKING | ${summary.breaking_count} |\n`;
    comment += `| Issues WARNING | ${summary.warning_count} |\n`;
    comment += `| Issues SUGGESTION | ${summary.suggestion_count} |\n\n`;

    if (summary.breaking_count > 0) {
        comment += `### Issues BREAKING\n\n`;
        for (const category of Object.keys(report.issues || {})) {
            for (const issue of report.issues[category]) {
                if (issue.severity === 'breaking') {
                    comment += `- **[${category.toUpperCase()}]** ${issue.message}\n`;
                }
            }
        }
        comment += `\n`;
    }

    comment += `---\n`;
    comment += `*Genere par [sylius-upgrade-analyzer](https://github.com/pierrearthurdemengel/sylius-upgrade-analyzer)*\n`;

    // Poster le commentaire via gh cli
    const prNumber = process.env.GITHUB_REF?.match(/refs\/pull\/(\d+)/)?.[1];
    if (!prNumber) {
        console.log('Pas de PR detectee. Commentaire non poste.');
        console.log(comment);
        return;
    }

    const repo = process.env.GITHUB_REPOSITORY;

    try {
        fs.writeFileSync('/tmp/pr-comment.md', comment);
        execSync(`gh pr comment ${prNumber} --repo ${repo} --body-file /tmp/pr-comment.md`, {
            env: { ...process.env, GH_TOKEN: process.env.GITHUB_TOKEN }
        });
        console.log(`Commentaire poste sur la PR #${prNumber}`);
    } catch (error) {
        console.error('Erreur lors du post du commentaire:', error.message);
        process.exit(1);
    }
}

main().catch(console.error);
