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
