/**
 * Custom Dialog Box System
 * Replaces alert() with beautiful UI dialogs
 */

class Dialog {
    constructor() {
        this.createDialogContainer();
    }

    createDialogContainer() {
        if (document.getElementById('customDialog')) return;

        const dialogHTML = `
            <div id="customDialog" class="custom-dialog hidden">
                <div class="dialog-overlay"></div>
                <div class="dialog-box">
                    <div class="dialog-icon" id="dialogIcon"></div>
                    <h3 class="dialog-title" id="dialogTitle"></h3>
                    <p class="dialog-message" id="dialogMessage"></p>
                    <div class="dialog-buttons" id="dialogButtons"></div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', dialogHTML);
    }

    show(options) {
        const {
            title = 'Notice',
            message = '',
            type = 'info', // info, success, warning, error
            buttons = [{ text: 'OK', primary: true }],
            onClose = null
        } = options;

        const dialog = document.getElementById('customDialog');
        const icon = document.getElementById('dialogIcon');
        const titleEl = document.getElementById('dialogTitle');
        const messageEl = document.getElementById('dialogMessage');
        const buttonsEl = document.getElementById('dialogButtons');

        // Set icon based on type
        const icons = {
            info: '💬',
            success: '✅',
            warning: '⚠️',
            error: '❌'
        };
        icon.textContent = icons[type] || icons.info;
        icon.className = 'dialog-icon ' + type;

        // Set content
        titleEl.textContent = title;
        messageEl.textContent = message;

        // Create buttons
        buttonsEl.innerHTML = '';
        buttons.forEach(btn => {
            const button = document.createElement('button');
            button.textContent = btn.text;
            button.className = btn.primary ? 'dialog-btn primary' : 'dialog-btn';
            button.onclick = () => {
                this.hide();
                if (btn.onClick) btn.onClick();
                if (onClose) onClose(btn.text);
            };
            buttonsEl.appendChild(button);
        });

        // Show dialog
        dialog.classList.remove('hidden');
        
        // Focus first button
        setTimeout(() => {
            const firstBtn = buttonsEl.querySelector('button');
            if (firstBtn) firstBtn.focus();
        }, 100);
    }

    hide() {
        const dialog = document.getElementById('customDialog');
        dialog.classList.add('hidden');
    }

    confirm(options) {
        return new Promise((resolve) => {
            this.show({
                ...options,
                type: options.type || 'warning',
                buttons: [
                    {
                        text: 'Cancel',
                        onClick: () => resolve(false)
                    },
                    {
                        text: options.confirmText || 'Confirm',
                        primary: true,
                        onClick: () => resolve(true)
                    }
                ]
            });
        });
    }

    alert(message, type = 'info', title = null) {
        const titles = {
            info: 'Information',
            success: 'Success',
            warning: 'Warning',
            error: 'Error'
        };
        
        this.show({
            title: title || titles[type],
            message: message,
            type: type,
            buttons: [{ text: 'OK', primary: true }]
        });
    }

    success(message, title = 'Success') {
        this.alert(message, 'success', title);
    }

    error(message, title = 'Error') {
        this.alert(message, 'error', title);
    }

    warning(message, title = 'Warning') {
        this.alert(message, 'warning', title);
    }

    info(message, title = 'Information') {
        this.alert(message, 'info', title);
    }
}

// Create global instance
const dialog = new Dialog();

// Override default alert (optional)
// window.alert = (msg) => dialog.alert(msg);
