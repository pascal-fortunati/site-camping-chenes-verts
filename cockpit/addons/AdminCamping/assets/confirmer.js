/**
 * Une demande de confirmation claire : un titre, une explication et un bouton qui dit ce qu'il fait
 * (« Supprimer », en rouge) au lieu du « OK » de Cockpit. Même fenêtre que App.ui.confirm, mieux remplie.
 */

const echapper = (texte) => String(texte).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/**
 * @param {{titre: string, texte?: string, details?: string[], bouton?: string, danger?: boolean, icone?: string}} options
 * @returns {Promise<boolean>} true si la personne confirme
 */
export default function confirmer({ titre, texte = '', details = [], bouton = 'Confirmer', danger = false, icone = null }) {
    return new Promise((resoudre) => {
        const liste = details.length ? `<ul class="dlg-details">${details.map((d) => `<li>${echapper(d)}</li>`).join('')}</ul>` : '';
        const fenetre = App.ui.dialog(/*html*/`
            <div class="dlg" role="alertdialog" aria-labelledby="dlg-titre">
                <span class="dlg-icone${danger ? ' dlg-icone--danger' : ''}" aria-hidden="true"><icon>${icone || (danger ? 'delete' : 'help')}</icon></span>
                <h2 class="dlg-titre" id="dlg-titre">${echapper(titre)}</h2>
                ${texte ? `<p class="dlg-texte">${echapper(texte)}</p>` : ''}
                ${liste}
                <div class="dlg-boutons">
                    <button type="button" class="kiss-button dlg-annuler">Annuler</button>
                    <button type="button" class="kiss-button ${danger ? 'kiss-button-danger' : 'kiss-button-primary'} dlg-ok"><icon>${danger ? 'delete' : 'check'}</icon>${echapper(bouton)}</button>
                </div>
            </div>
        `, { escape: true }, 'confirm');

        let repondu = false;
        const repondre = (oui) => {
            if (repondu) return;
            repondu = true;
            resoudre(oui);
            fenetre.close();
        };

        fenetre.querySelector('.dlg-ok').addEventListener('click', () => repondre(true));
        fenetre.querySelector('.dlg-annuler').addEventListener('click', () => repondre(false));
        fenetre.addEventListener('dialogclose', () => repondre(false));
        fenetre.show();
        setTimeout(() => fenetre.querySelector('.dlg-annuler').focus(), 50);
    });
}
