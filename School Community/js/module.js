/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

window.schoolCommunitySetGroupTargetType = function (type) {
    const groupFields = document.querySelectorAll('.schoolCommunityGroupTargetField');
    const groupMode = Array.from(groupFields).some((field) => !field.classList.contains('hidden'));
    const showFree = groupMode && type === 'free';

    document.querySelectorAll('.schoolCommunityFreeNotice, .schoolCommunityFreeCreateGroup').forEach((row) => {
        row.classList.toggle('hidden', !showFree);
    });

    document.querySelectorAll('.schoolCommunityGroupTargetPicker').forEach((row) => {
        const active = groupMode && type !== '' && row.classList.contains('schoolCommunityGroupTargetPicker-'+type);
        row.classList.toggle('hidden', !active);
        row.querySelectorAll('select').forEach((field) => {
            field.disabled = !active;
        });
    });
};

window.schoolCommunitySetMessageMode = function (mode) {
    const groupMode = mode === 'Y';

    document.querySelectorAll('.schoolCommunityDirectField').forEach((row) => {
        row.classList.toggle('hidden', groupMode);
        row.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = groupMode;
        });
    });
    document.querySelectorAll('.schoolCommunityDirectSubmit').forEach((button) => {
        button.classList.toggle('hidden', groupMode);
        button.disabled = groupMode;
    });

    document.querySelectorAll('.schoolCommunityGroupTargetField').forEach((row) => {
        row.classList.toggle('hidden', !groupMode);
        row.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !groupMode;
        });
    });
    document.querySelectorAll('.schoolCommunityGroupSubmit').forEach((button) => {
        button.classList.toggle('hidden', !groupMode);
        button.disabled = !groupMode;
    });

    const targetType = document.querySelector('select[name="targetType"]');
    window.schoolCommunitySetGroupTargetType(groupMode && targetType ? targetType.value : '');
};

window.schoolCommunityUpdateFreeRecipientCount = function () {
    const form = document.getElementById('schoolCommunityFreeRecipients');
    if (!form) {
        return;
    }
    const boxes = form.querySelectorAll('input[name="recipientIDs[]"]');
    const checked = form.querySelectorAll('input[name="recipientIDs[]"]:checked');
    const counter = document.getElementById('schoolCommunityFreeRecipientCount');
    if (counter) {
        counter.textContent = String(checked.length);
    }
    boxes.forEach(function (box) {
        box.disabled = checked.length >= 5 && !box.checked;
    });
};

window.schoolCommunityFilterFreeRecipients = function (term) {
    const form = document.getElementById('schoolCommunityFreeRecipients');
    if (!form) {
        return;
    }
    const needle = (term || '').trim().toLowerCase();
    form.querySelectorAll('input[name="recipientIDs[]"]').forEach(function (box) {
        const row = box.closest('div');
        const label = row ? row.querySelector('label') : null;
        const text = label ? label.textContent.toLowerCase() : '';
        if (row) {
            row.classList.toggle('hidden', needle !== '' && text.indexOf(needle) === -1);
        }
    });
};

document.addEventListener('DOMContentLoaded', function () {
    const mode = document.getElementById('groupMessage');
    if (mode) {
        window.schoolCommunitySetMessageMode(mode.value);
    }
    window.schoolCommunityUpdateFreeRecipientCount();
});

if (typeof htmx !== 'undefined') {
    htmx.onLoad(function () {
        window.schoolCommunityUpdateFreeRecipientCount();
    });
}

['change', 'input'].forEach(function (eventName) {
    document.addEventListener(eventName, function (event) {
        if (!event.target) {
            return;
        }
        if (event.target.id === 'groupMessage') {
            window.schoolCommunitySetMessageMode(event.target.value);
        }
        if (event.target.id === 'targetType') {
            window.schoolCommunitySetGroupTargetType(event.target.value);
        }
        if (event.target.name === 'recipientIDs[]') {
            window.schoolCommunityUpdateFreeRecipientCount();
        }
        if (event.target.id === 'recipientSearch') {
            window.schoolCommunityFilterFreeRecipients(event.target.value);
        }
    });
});

// Group member picker: live selected count/names + name filter.
window.scGroupUpdatePreview = function () {
    var form = document.getElementById('schoolCommunityGroupAdd');
    if (!form) return;
    var checked = form.querySelectorAll('input[name="memberIDs[]"]:checked');
    var count = document.getElementById('scGroupSelectedCount');
    if (count) count.textContent = String(checked.length);
    var names = [];
    checked.forEach(function (box) {
        var label = form.querySelector('label[for="' + box.id + '"]');
        if (label) names.push(label.textContent.trim());
    });
    var preview = document.getElementById('scGroupSelectedNames');
    if (preview) preview.textContent = names.join('、');
};
window.scGroupFilterMembers = function (term) {
    var form = document.getElementById('schoolCommunityGroupAdd');
    if (!form) return;
    var needle = (term || '').trim().toLowerCase();
    form.querySelectorAll('input[name="memberIDs[]"]').forEach(function (box) {
        var row = box.closest('div');
        var label = row ? row.querySelector('label') : null;
        var text = label ? label.textContent.toLowerCase() : '';
        if (row) row.classList.toggle('hidden', needle !== '' && text.indexOf(needle) === -1);
    });
};
document.addEventListener('DOMContentLoaded', function () {
    window.scGroupUpdatePreview();
    document.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'memberIDs[]') window.scGroupUpdatePreview();
    });
    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'scGroupMemberSearch') window.scGroupFilterMembers(e.target.value);
    });
});
