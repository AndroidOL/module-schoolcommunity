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
    const targetForm = document.getElementById('schoolCommunityGroupTarget');

    if (directForm) {
        directForm.classList.toggle('hidden', groupMode);
        directForm.setAttribute('aria-hidden', groupMode ? 'true' : 'false');
    }
    if (targetForm) {
        targetForm.classList.toggle('hidden', !groupMode);
        targetForm.setAttribute('aria-hidden', groupMode ? 'false' : 'true');
    }

    if (!groupMode) {
        window.schoolCommunitySetGroupTargetType('');
        return;
    }

    const targetType = targetForm?.querySelector('select[name="targetType"]')?.value || 'free';
    window.schoolCommunitySetGroupTargetType(targetType);
};

window.schoolCommunitySetGroupTargetType = function (type) {
    const freeForm = document.getElementById('schoolCommunityFreeGroup');
    const targetForm = document.getElementById('schoolCommunityGroupTarget');
    const targetTypeInput = targetForm
        ? targetForm.querySelector('select[name="targetType"]')
        : null;
    const groupMode = targetForm !== null && !targetForm.classList.contains('hidden');

    if (freeForm) {
        const showFree = groupMode && type === 'free';
        freeForm.classList.toggle('hidden', !showFree);
        freeForm.setAttribute('aria-hidden', showFree ? 'false' : 'true');
    }
    if (targetTypeInput && type !== '') {
        targetTypeInput.value = type;
    }

    document.querySelectorAll('.schoolCommunityGroupSubmit').forEach((row) => {
        row.classList.toggle('hidden', !groupMode || type === '' || type === 'free');
    });
    document.querySelectorAll('.schoolCommunityGroupTargetPicker').forEach((row) => {
        const active = groupMode && type !== '' && row.classList.contains('schoolCommunityGroupTargetPicker-'+type);
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
        if (!event.target) {
            return;
        }
        if (event.target.id === 'groupMessage') {
            window.schoolCommunitySetMessageMode(event.target.value);
        }
        if (event.target.id === 'targetType') {
            window.schoolCommunitySetGroupTargetType(event.target.value);
        }
    });
});
