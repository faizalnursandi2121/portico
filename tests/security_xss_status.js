'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

class FakeElement {
    constructor() {
        this.children = [];
        this._text = '';
        this._html = '';
    }

    append(...children) {
        this.children.push(...children);
    }

    replaceChildren(...children) {
        this.children = children;
        this._text = '';
        this._html = '';
    }

    set textContent(value) {
        this.children = [];
        this._html = '';
        this._text = String(value);
    }

    get textContent() {
        return this._text + this.children.map((child) => child.textContent).join('');
    }

    set innerHTML(value) {
        this.children = [];
        this._text = '';
        this._html = String(value);
    }

    get innerHTML() {
        return this._html;
    }
}

const rendererPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'status-renderer.js');
if (!fs.existsSync(rendererPath)) {
    throw new Error('FAIL: status renderer does not exist');
}

const context = {
    window: {
        Mivo: {
            registerModule() {},
        },
    },
    document: {
        createElement: () => new FakeElement(),
    },
};

vm.runInNewContext(fs.readFileSync(rendererPath, 'utf8'), context, { filename: rendererPath });

const hostileValue = '<img src=x onerror=alert(1)>';
const root = context.window.renderStatusDetails({ username: hostileValue }, (key) => key);

function usesInnerHtml(node) {
    return node.innerHTML !== '' || node.children.some(usesInnerHtml);
}

if (!(root instanceof FakeElement)
    || usesInnerHtml(root)
    || !root.textContent.includes(hostileValue)) {
    throw new Error('FAIL: status renderer accepted executable HTML');
}

let alertConfig;
context.Swal = {
    fire(config) {
        alertConfig = config;
        return config;
    },
};
const alertPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'modules', 'alert.js');
vm.runInNewContext(fs.readFileSync(alertPath, 'utf8'), context, { filename: alertPath });
context.window.Mivo.alert('success', 'Status', root);

if (alertConfig.html !== root) {
    throw new Error('FAIL: Mivo.alert did not pass the safe DOM node to SweetAlert2');
}

console.log('PASS: public status renderer and alert integration escape untrusted values');
