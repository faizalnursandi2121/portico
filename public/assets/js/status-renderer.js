(function (window, document) {
    'use strict';

    const statusClasses = {
        active: 'bg-emerald-500/10 text-emerald-600 border-emerald-200 dark:border-emerald-800 dark:text-emerald-400',
        expired: 'bg-slate-500/10 text-slate-600 border-slate-200 dark:border-slate-800 dark:text-slate-400',
        limited: 'bg-orange-500/10 text-orange-600 border-orange-200 dark:border-orange-800 dark:text-orange-400',
        locked: 'bg-red-500/10 text-red-600 border-red-200 dark:border-red-800 dark:text-red-400',
    };

    function element(tag, className, text) {
        const node = document.createElement(tag);
        node.className = className;
        if (text !== undefined) {
            node.textContent = String(text ?? '-');
        }

        return node;
    }

    function detailRow(label, value) {
        const row = element('tr', 'border-t border-white/10');
        row.append(
            element('td', 'py-3 text-accents-5 font-bold uppercase tracking-wide text-[10px]', label),
            element('td', 'py-3 text-right font-bold text-foreground font-mono', value)
        );

        return row;
    }

    window.renderStatusDetails = function (data, translate) {
        data = data || {};
        const t = typeof translate === 'function' ? translate : (key) => key;
        const status = String(data.status ?? 'unknown');
        const root = element('div', 'text-left mt-6 relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-md shadow-2xl ring-1 ring-black/5 dark:ring-white/5');
        const topDecoration = element('div', 'absolute -top-10 -right-10 w-32 h-32 bg-blue-500/20 rounded-full blur-3xl pointer-events-none');
        const bottomDecoration = element('div', 'absolute -bottom-10 -left-10 w-32 h-32 bg-purple-500/20 rounded-full blur-3xl pointer-events-none');
        const header = element('div', 'relative p-5 md:p-6 border-b border-white/10 flex justify-between items-center bg-white/10 dark:bg-black/20');
        const code = element('div', '');
        code.append(
            element('span', 'text-[10px] text-accents-5 font-bold uppercase tracking-widest block mb-0.5', t('status.code')),
            element('span', 'font-mono text-xl md:text-2xl font-black tracking-tighter text-foreground', data.username)
        );
        header.append(
            code,
            element('div', `px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] border shadow-sm backdrop-blur-sm bg-opacity-80 ${statusClasses[status.toLowerCase()] ?? 'bg-blue-500/10 text-blue-600 border-blue-200 dark:border-blue-800 dark:text-blue-400'}`, status)
        );

        const usage = element('div', 'relative p-5 md:p-6 pb-2');
        const usageHeading = element('div', 'flex justify-between items-end mb-2');
        usageHeading.append(
            element('span', 'text-xs font-bold text-accents-5 uppercase tracking-wide', t('status.data_remaining')),
            element('span', 'text-lg font-black text-blue-600 dark:text-blue-400 font-mono tracking-tight', data.data_left)
        );
        const bar = element('div', 'w-full h-2.5 bg-accents-2 rounded-full overflow-hidden shadow-inner ring-1 ring-black/5 dark:ring-white/5');
        const progress = element('div', 'h-full w-full bg-gradient-to-r from-blue-500 to-indigo-600 shadow-lg relative overflow-hidden');
        progress.append(element('div', 'absolute inset-0 bg-white/20 animate-[shimmer_2s_infinite]'));
        bar.append(progress);
        const used = element('div', 'text-right mt-1.5');
        const usedLabel = element('span', 'text-[10px] font-semibold text-accents-4 uppercase tracking-wider', `${t('status.used')}: `);
        usedLabel.append(element('span', 'text-foreground', data.data_used));
        used.append(usedLabel);
        usage.append(usageHeading, bar, used);

        const details = element('table', 'w-full text-sm text-left');
        const body = element('tbody', 'divide-y divide-white/10');
        body.append(
            detailRow(t('status.package'), data.profile),
            detailRow(t('status.validity'), data.validity),
            detailRow(t('status.uptime'), data.uptime_used),
            detailRow(t('status.expires'), data.expiration)
        );
        details.append(body);
        const detailsWrapper = element('div', 'p-5 md:p-6 pt-2');
        detailsWrapper.append(details);

        root.replaceChildren(topDecoration, bottomDecoration, header, usage, detailsWrapper);

        return root;
    };
})(window, document);
