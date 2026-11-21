/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

window.schoolCommunitySetMessageMode = function (mode) {
    const directForm = document.getElementById('schoolCommunityDirectMessage');
    const groupMode = mode === 'Y';
    const targetTypeRow = document.querySelector('.schoolCommunityTargetTypeRow');

    if (directForm) {
        directForm.classList.toggle('hidden', groupMode);
        directForm.setAttribute('aria-hidden', groupMode ? 'true' : 'false');
    }
    if (targetTypeRow) {
        targetTypeRow.classList.toggle('hidden', !groupMode);
    }

    window.schoolCommunitySetGroupTargetType(
        groupMode ? (document.getElementById('targetType')?.value || 'free') : ''
    );
};

window.schoolCommunitySetGroupTargetType = function (type) {
    const freeForm = document.getElementById('schoolCommunityFreeGroup');
    const targetForm = document.getElementById('schoolCommunityGroupTarget');
    const targetTypeInput = targetForm
        ? targetForm.querySelector('input[name="targetType"]')
        : null;

    if (freeForm) {
        freeForm.classList.toggle('hidden', type !== 'free');
        freeForm.setAttribute('aria-hidden', type === 'free' ? 'false' : 'true');
    }
    if (targetForm) {
        targetForm.classList.toggle('hidden', type === '' || type === 'free');
        targetForm.setAttribute('aria-hidden', type === '' || type === 'free' ? 'true' : 'false');
    }
    if (targetTypeInput && type !== '') {
        targetTypeInput.value = type;
    }

    document.querySelectorAll('.schoolCommunityGroupTargetPicker').forEach((row) => {
        const active = type !== '' && row.classList.contains('schoolCommunityGroupTargetPicker-'+type);
        row.classList.toggle('hidden', !active);
        row.querySelectorAll('select').forEach((field) => {
            field.disabled = !active;
        });
    });
};

document.addEventListener('DOMContentLoaded', function () {
    const mode = document.getElementById('groupMessage');
    if (mode) {
        window.schoolCommunitySetMessageMode(mode.value);
    }
});

['change', 'input'].forEach(function (eventName) {
    document.addEventListener(eventName, function (event) {
        if (event.target && event.target.id === 'groupMessage') {
            window.schoolCommunitySetMessageMode(event.target.value);
        }
    });
});
