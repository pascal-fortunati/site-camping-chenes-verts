/**
 * Cockpit's notifications are written in English and, unlike its dialogs, never go through the dictionary:
 * « Data updated! » stayed in English even though i18n/fr.php translates it. Every message now does.
 */

const notify = App.ui.notify.bind(App.ui);

App.ui.notify = (message, ...rest) => notify(typeof message === 'string' ? App.i18n.get(message) : message, ...rest);
