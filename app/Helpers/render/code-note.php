<?php

use App\Enums\Permissions;
use App\Models\User;

function RenderCodeNotes(array $codeNotes, ?User $editingUser = null, ?int $editingPermissions = null): void
{
    $isEditable = $editingUser && $editingPermissions >= Permissions::JuniorDeveloper;

    echo "<table class='table-highlight'>";

    echo "<thead>";
    echo "<tr class='do-not-highlight'>";
    echo "<th style='font-size:100%;'>Mem</th>";
    echo "<th style='font-size:100%;'>Note</th>";
    echo "<th style='font-size:100%;'>Author</th>";
    if ($isEditable) {
        echo "<th>Dev</th>";
    }
    echo "</tr>";
    echo "</thead>";

    echo "<tbody>";

    $rowIndex = 0;
    foreach ($codeNotes as $nextCodeNote) {
        if (empty(trim($nextCodeNote['Note'])) || $nextCodeNote['Note'] == "''") {
            continue;
        }

        $canEditNote = (
            $editingPermissions >= Permissions::Developer
            || ($editingPermissions === Permissions::JuniorDeveloper && $nextCodeNote['User'] === $editingUser?->display_name)
        );

        echo "<tr id='row-$rowIndex' class='note-row'>";

        $addr = $nextCodeNote['Address'];
        $addrInt = hexdec($addr);

        $addrFormatted = sprintf("%04x", $addrInt);
        $originalMemNote = $nextCodeNote['Note'];

        sanitize_outputs($originalMemNote);

        $memNote = nl2br($originalMemNote);

        echo "<td data-address='$addr' style='width: 25%;'>";
        echo "<span class='font-mono'>0x$addrFormatted</span>";
        echo "</td>";

        echo <<<HTML
            <td>
                <div class="font-mono note-display block" style="word-break: break-word;">$memNote</div>
                <textarea class="w-full font-mono note-edit hidden">$originalMemNote</textarea>
                <div class="mt-[6px] flex justify-between">
                    <button class="btn save-btn hidden" type="button" onclick="saveCodeNote($rowIndex)">Save</button>
                    <button class="btn delete-btn btn-danger hidden" type="button" onclick="deleteCodeNote($rowIndex)">Delete</button>
                </div>
            </td>
        HTML;

        echo "<td class='note-author-avatar' data-current-author='" . $nextCodeNote['User'] . "'>";
        echo userAvatar($nextCodeNote['User'], label: false, iconSize: 24);
        echo "</td>";

        if ($canEditNote) {
            echo "<td>";
            echo "<button class='btn edit-btn inline' type='button' onclick='beginEditMode($rowIndex)'>Edit</button>";
            echo "<button class='btn cancel-btn hidden' type='button' onclick='cancelEditMode($rowIndex)'>Cancel</button>";
            echo "</td>";
        } elseif ($isEditable) {
            echo "<td></td>";
        }

        echo "</tr>";

        $rowIndex++;
    }

    echo "</tbody></table>";
}
