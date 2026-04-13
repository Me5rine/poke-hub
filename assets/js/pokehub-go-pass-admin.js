(function ($) {
    'use strict';

    function L(k, fb) {
        var o = window.pokehubGoPassL10n || {};
        return o[k] || fb || '';
    }

    function escAttr(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function nextBracketIndex($tbody) {
        var max = -1;
        $tbody.find('tr').each(function () {
            var $first = $(this).find('input, textarea, select').first();
            var name = $first.attr('name');
            if (!name) {
                return;
            }
            var m = name.match(/\[(\d+)\]/);
            if (m) {
                var n = parseInt(m[1], 10);
                if (!isNaN(n) && n > max) {
                    max = n;
                }
            }
        });
        return max + 1;
    }

    function nextRewardIndex($list) {
        var max = -1;
        $list.find('.pokehub-go-pass-reward-editor').each(function () {
            var $t = $(this).find('.pokehub-reward-type').first();
            var name = $t.attr('name');
            if (!name) {
                return;
            }
            var m = name.match(/\[(\d+)\]\[type\]$/);
            if (!m) {
                m = name.match(/\[(\d+)\]\[type\]/);
            }
            if (m) {
                var n = parseInt(m[1], 10);
                if (!isNaN(n) && n > max) {
                    max = n;
                }
            }
        });
        return max + 1;
    }

    function goPassUpdateRewardBranches($editor) {
        var rewardType = $editor.find('.pokehub-reward-type').val();
        var isPokemon = rewardType === 'pokemon';
        var isItem = rewardType === 'item';
        var isStardust = rewardType === 'stardust';
        var isXp = rewardType === 'xp';
        var isCandy = rewardType === 'candy';
        var isXlCandy = rewardType === 'xl_candy';
        var isMegaEnergy = rewardType === 'mega_energy';
        var isPokemonResource = isCandy || isXlCandy || isMegaEnergy;
        var isBonus = rewardType === 'bonus';

        $editor.find('.pokehub-reward-pokemon-fields').toggle(isPokemon);
        $editor.find('.pokehub-reward-pokemon-resource-fields').toggle(isPokemonResource);
        $editor.find('.pokehub-go-pass-reward-bonus-fields').toggle(isBonus);
        $editor.find('.pokehub-reward-other-fields').toggle(!isPokemon && !isPokemonResource && !isBonus);
        $editor.find('.pokehub-reward-item-name-field').toggle(isItem);
        $editor.find('.pokehub-reward-quantity-field').each(function () {
            var $q = $(this);
            var inResource = $q.closest('.pokehub-reward-pokemon-resource-fields').length > 0;
            if (inResource) {
                $q.toggle(isPokemonResource);
            } else {
                $q.toggle(isStardust || isXp || isItem);
            }
        });

        // Inclure textarea (ex. description bonus) : sinon le champ reste disabled après passage au type « bonus ».
        $editor.find('.pokehub-reward-pokemon-fields, .pokehub-reward-pokemon-resource-fields, .pokehub-reward-other-fields, .pokehub-go-pass-reward-bonus-fields').each(function () {
            var $block = $(this);
            var on = $block.is(':visible');
            $block.find('select, input, textarea').prop('disabled', !on);
        });
    }

    function initGoPassRewardSelects($ctx) {
        if (!$ctx || !$ctx.length) {
            return;
        }
        if (window.pokehubInitQuestPokemonSelect2) {
            window.pokehubInitQuestPokemonSelect2($ctx);
        }
        if (window.pokehubInitQuestItemSelect2) {
            window.pokehubInitQuestItemSelect2($ctx);
        }
        if (window.pokehubInitGoPassBonusRewardSelect2) {
            window.pokehubInitGoPassBonusRewardSelect2($ctx);
        }
    }

    function bonusCatalogOptionsHtml() {
        var opts = (window.pokehubGoPassL10n && window.pokehubGoPassL10n.bonusOptions) ? window.pokehubGoPassL10n.bonusOptions : [];
        var h = '<option value="0">' + escAttr(L('bonusNone')) + '</option>';
        var j;
        for (j = 0; j < opts.length; j++) {
            var o = opts[j];
            if (!o || o.id == null) {
                continue;
            }
            h += '<option value="' + escAttr(String(o.id)) + '">' + escAttr(String(o.label || '')) + '</option>';
        }
        return h;
    }

    function rewardRowHtml(prefix, rewardIndex) {
        var nb = prefix + '[' + rewardIndex + ']';
        return (
            '<div class="pokehub-quest-reward-editor pokehub-go-pass-reward-editor" style="margin-top:8px;padding:8px;background:#fff;border:1px solid #ccd0d4;">' +
            '<label>' +
            L('rewardType', 'Type') +
            ' <select name="' +
            nb +
            '[type]" class="pokehub-reward-type">' +
            '<option value="xp" selected>' +
            L('xp', 'XP') +
            '</option>' +
            '<option value="stardust">' +
            L('stardust', 'Stardust') +
            '</option>' +
            '<option value="item">' +
            L('item', 'Item') +
            '</option>' +
            '<option value="pokemon">' +
            L('pokemon', 'Pokémon') +
            '</option>' +
            '<option value="candy">' +
            L('candy', 'Candy') +
            '</option>' +
            '<option value="xl_candy">' +
            L('xlCandy', 'XL Candy') +
            '</option>' +
            '<option value="mega_energy">' +
            L('megaEnergy', 'Mega Energy') +
            '</option>' +
            '<option value="bonus">' +
            escAttr(L('rewardBonus', 'Bonus')) +
            '</option>' +
            '</select></label>' +
            '<p style="margin:6px 0 0;"><label><input type="checkbox" name="' +
            nb +
            '[featured]" value="1" /> ' +
            escAttr(L('featuredReward')) +
            '</label></p>' +
            '<div class="pokehub-go-pass-reward-bonus-fields" style="display:none;">' +
            '<label>' +
            escAttr(L('bonusCol', 'Bonus')) +
            ' : <select name="' +
            nb +
            '[bonus_id]" class="pokehub-go-pass-bonus-reward-select" style="width:100%;min-width:240px;" disabled data-placeholder="' +
            escAttr(L('bonusNone')) +
            '">' +
            bonusCatalogOptionsHtml() +
            '</select></label>' +
            '<label style="display:block;margin-top:8px;">' +
            L('rewardBonusDesc', 'Texte complémentaire (optionnel)') +
            ' : <textarea name="' +
            nb +
            '[description]" class="large-text" rows="2" style="width:100%;" disabled></textarea></label></div>' +
            '<div class="pokehub-reward-pokemon-fields" style="display:none;">' +
            '<label>' +
            L('pokemon', 'Pokémon') +
            ' (' +
            L('oneCopy', '×1') +
            ') : ' +
            '<select name="' +
            nb +
            '[pokemon_id]" class="pokehub-select-pokemon pokehub-quest-pokemon-select" style="width:100%;min-width:250px;" disabled data-placeholder="' +
            escAttr(L('searchPokemon')) +
            '"></select></label>' +
            '<div class="pokehub-go-pass-pokemon-flags" style="margin-top:8px;">' +
            '<label style="display:inline-block;margin-right:10px;"><input type="checkbox" name="' +
            nb +
            '[force_shiny]" value="1" disabled /> ' +
            escAttr(L('flagShiny')) +
            '</label>' +
            '<label style="display:inline-block;margin-right:10px;"><input type="checkbox" name="' +
            nb +
            '[force_shadow]" value="1" disabled /> ' +
            escAttr(L('flagShadow')) +
            '</label>' +
            '<label style="display:inline-block;margin-right:10px;"><input type="checkbox" name="' +
            nb +
            '[force_dynamax]" value="1" disabled /> ' +
            escAttr(L('flagDynamax')) +
            '</label>' +
            '<label style="display:inline-block;margin-right:10px;"><input type="checkbox" name="' +
            nb +
            '[force_gigamax]" value="1" disabled /> ' +
            escAttr(L('flagGigamax')) +
            '</label>' +
            '</div></div>' +
            '<div class="pokehub-reward-pokemon-resource-fields" style="display:none;">' +
            '<label>' +
            L('pokemon', 'Pokémon') +
            ': <select name="' +
            nb +
            '[pokemon_id]" class="pokehub-select-pokemon-resource" style="width:100%;min-width:250px;" disabled>' +
            '<option value="">' +
            escAttr(L('selectPokemon')) +
            '</option></select></label>' +
            '<label class="pokehub-reward-quantity-field" style="display:none;">' +
            L('quantity', 'Qty') +
            ': <input type="number" name="' +
            nb +
            '[quantity]" value="1" min="1" disabled /></label></div>' +
            '<div class="pokehub-reward-other-fields" style="display:block;">' +
            '<label class="pokehub-reward-quantity-field" style="display:block;">' +
            L('quantity', 'Qty') +
            ': <input type="number" name="' +
            nb +
            '[quantity]" value="1" min="1" /></label>' +
            '<label class="pokehub-reward-item-name-field" style="display:none;">' +
            L('item', 'Item') +
            ': <select name="' +
            nb +
            '[item_id]" class="pokehub-select-item" style="width:100%;min-width:250px;" disabled>' +
            '<option value=""></option></select></label></div>' +
            '<p style="margin:8px 0 0;"><button type="button" class="button-link pokehub-gp-remove-reward">' +
            escAttr(L('removeReward')) +
            '</button></p></div>'
        );
    }

    function goPassReindexGpTierRowNames(newIndex, $tr) {
        $tr.find('input,select,textarea').each(function () {
            var el = this;
            if (el.name && el.name.indexOf('gp_tiers[') === 0) {
                el.name = el.name.replace(/^gp_tiers\[\d+]/, 'gp_tiers[' + newIndex + ']');
            }
        });
        $tr.find('.pokehub-gp-add-reward').each(function () {
            var p = $(this).attr('data-prefix');
            if (p && p.indexOf('gp_tiers[') === 0) {
                $(this).attr('data-prefix', p.replace(/^gp_tiers\[\d+]/, 'gp_tiers[' + newIndex + ']'));
            }
        });
    }

    function goPassReindexTierRows() {
        var $tbody = $('#pokehub-gp-tier-body');
        if (!$tbody.length) {
            return;
        }
        $tbody.find('tr.pokehub-gp-tier-row').each(function (idx) {
            goPassReindexGpTierRowNames(idx, $(this));
        });
    }

    /** Évite les re-entrées (change sur rank_to / .val() en cascade) qui figent l’admin. */
    var goPassApplyingTierChain = false;
    var goPassRankChainDebounceTimer = null;

    /** Palier bonus = au moins une récompense de type « bonus » sur la ligne. */
    function goPassTierRowHasBonus($tr) {
        var found = false;
        $tr.find('.pokehub-reward-type').each(function () {
            if ($(this).val() === 'bonus') {
                found = true;
                return false;
            }
        });
        return found;
    }

    /**
     * Première ligne « récompenses de rang » (sans type bonus) : rang début libre si aucune ligne
     * au-dessus n’occupe déjà des rangs ; sinon chaînage comme les suivantes.
     * Lignes suivantes : rang début = fin de la plage occupée par les lignes précédentes + 1.
     * Palier bonus : un seul rang (début) ; compte pour la chaîne des lignes suivantes.
     */
    function goPassSyncTierRanksFromChain() {
        if (goPassApplyingTierChain) {
            return;
        }
        var $tbody = $('#pokehub-gp-tier-body');
        if (!$tbody.length) {
            return;
        }
        goPassApplyingTierChain = true;
        try {
            var lastOccupiedEnd = 0;
            var firstNonBonusSeen = false;
            $tbody.find('tr.pokehub-gp-tier-row').each(function () {
                var $tr = $(this);
                var isBonusTier = goPassTierRowHasBonus($tr);
                var $rank = $tr.find('input.pokehub-gp-tier-rank-start');
                if (!$rank.length) {
                    return;
                }

                if (isBonusTier) {
                    $rank.prop('readonly', false);
                    var bRank = parseInt($rank.val(), 10) || 1;
                    lastOccupiedEnd = bRank;
                    return;
                }

                var $rankTo = $tr.find('input.pokehub-gp-tier-rank-to').filter(function () {
                    return !this.disabled;
                });
                if (!$rankTo.length) {
                    $rankTo = $tr.find('input[name$="[rank_to]"]').filter(function () {
                        return $(this).attr('type') !== 'hidden' && !this.disabled;
                    });
                }
                var rToVal = parseInt($rankTo.val(), 10);
                var rFromVal = parseInt($rank.val(), 10) || 1;

                if (!firstNonBonusSeen) {
                    firstNonBonusSeen = true;
                    if (lastOccupiedEnd === 0) {
                        $rank.prop('readonly', false);
                        rFromVal = parseInt($rank.val(), 10) || 1;
                        var endFirst = !isNaN(rToVal) && rToVal >= rFromVal ? rToVal : rFromVal;
                        lastOccupiedEnd = endFirst;
                        return;
                    }
                    var nextAfterBonus = lastOccupiedEnd + 1;
                    var cur0 = parseInt($rank.val(), 10);
                    if (isNaN(cur0) || cur0 !== nextAfterBonus) {
                        $rank.val(String(nextAfterBonus));
                    }
                    $rank.prop('readonly', true);
                    rFromVal = nextAfterBonus;
                    var endAfter = !isNaN(rToVal) && rToVal >= rFromVal ? rToVal : rFromVal;
                    lastOccupiedEnd = endAfter;
                    return;
                }

                var nextStart = lastOccupiedEnd + 1;
                var cur = parseInt($rank.val(), 10);
                if (isNaN(cur) || cur !== nextStart) {
                    $rank.val(String(nextStart));
                }
                $rank.prop('readonly', true);
                rFromVal = nextStart;
                var end = !isNaN(rToVal) && rToVal >= rFromVal ? rToVal : rFromVal;
                lastOccupiedEnd = end;
            });
        } finally {
            goPassApplyingTierChain = false;
        }
    }

    function goPassScheduleTierRankSync() {
        if (goPassApplyingTierChain) {
            return;
        }
        if (goPassRankChainDebounceTimer) {
            clearTimeout(goPassRankChainDebounceTimer);
        }
        goPassRankChainDebounceTimer = setTimeout(function () {
            goPassRankChainDebounceTimer = null;
            goPassSyncTierRanksFromChain();
        }, 120);
    }

    function goPassFirstRewardRow() {
        return $('#pokehub-go-pass-form #pokehub-gp-tier-body tr.pokehub-gp-tier-row')
            .filter(function () {
                return !goPassTierRowHasBonus($(this));
            })
            .first();
    }

    function tierRowHtml(i) {
        var freeP = 'gp_tiers[' + i + '][free_rewards]';
        var premP = 'gp_tiers[' + i + '][premium_rewards]';
        return (
            '<tr class="pokehub-gp-tier-row">' +
            '<td class="pokehub-gp-tier-drag-cell"><span class="pokehub-gp-tier-drag-handle dashicons dashicons-menu" style="cursor:move;opacity:.75" title="' +
            escAttr(L('dragReorder', '')) +
            '"></span></td>' +
            '<td><input type="number" class="pokehub-gp-tier-rank-start" name="gp_tiers[' +
            i +
            '][rank]" min="1" value="1" required></td>' +
            '<td><span class="description pokehub-gp-tier-rank-to-dash" style="display:none;">—</span><input type="number" class="pokehub-gp-tier-rank-to" name="gp_tiers[' +
            i +
            '][rank_to]" min="1" value="" placeholder="' +
            escAttr(L('rankToPlaceholder')) +
            '" aria-label="' +
            escAttr(L('rankToPlaceholder')) +
            '"></td>' +
            '<td><div class="pokehub-go-pass-rewards-col"><div class="pokehub-go-pass-rewards-list">' +
            rewardRowHtml(freeP, 0) +
            '</div><p style="margin:8px 0 0;"><button type="button" class="button button-small pokehub-gp-add-reward" data-prefix="' +
            freeP +
            '">' +
            escAttr(L('addReward')) +
            '</button></p></div></td>' +
            '<td><div class="pokehub-go-pass-rewards-col"><div class="pokehub-go-pass-rewards-list">' +
            rewardRowHtml(premP, 0) +
            '</div><p style="margin:8px 0 0;"><button type="button" class="button button-small pokehub-gp-add-reward" data-prefix="' +
            premP +
            '">' +
            escAttr(L('addReward')) +
            '</button></p></div></td>' +
            '<td><button type="button" class="button pokehub-gp-remove-tier">&times;</button></td>' +
            '</tr>'
        );
    }

    function taskRowHtml(prefix, i) {
        return (
            '<tr class="pokehub-gp-' +
            prefix +
            '-row">' +
            '<td><input type="text" class="large-text" name="gp_' +
            prefix +
            '[' +
            i +
            '][label]" value=""></td>' +
            '<td><input type="number" name="gp_' +
            prefix +
            '[' +
            i +
            '][points]" min="0" value="0"></td>' +
            '<td><button type="button" class="button pokehub-gp-remove-' +
            prefix +
            '">&times;</button></td>' +
            '</tr>'
        );
    }

    function syncTierRowKind($tr) {
        var isM = goPassTierRowHasBonus($tr);
        var $dash = $tr.find('.pokehub-gp-tier-rank-to-dash');
        var $rankTo = $tr.find('input.pokehub-gp-tier-rank-to[name$="[rank_to]"]');
        if (!$rankTo.length) {
            $rankTo = $tr.find('input[name$="[rank_to]"]');
        }
        var prev = goPassApplyingTierChain;
        goPassApplyingTierChain = true;
        try {
            if (isM) {
                if ($dash.length) {
                    $dash.show();
                }
                $rankTo.prop('disabled', true).hide().val('');
            } else {
                if ($dash.length) {
                    $dash.hide();
                }
                $rankTo.prop('disabled', false).show();
            }
        } finally {
            goPassApplyingTierChain = prev;
        }
    }

    $(function () {
        var $form = $('#pokehub-go-pass-form');
        if (!$form.length) {
            return;
        }

        $form.on('submit', function () {
            if (goPassRankChainDebounceTimer) {
                clearTimeout(goPassRankChainDebounceTimer);
                goPassRankChainDebounceTimer = null;
            }
            goPassReindexTierRows();
            goPassSyncTierRanksFromChain();
            $form.find('.pokehub-go-pass-reward-editor').each(function () {
                goPassUpdateRewardBranches($(this));
            });
            $form.find('tr.pokehub-gp-tier-row').each(function () {
                syncTierRowKind($(this));
            });
        });

        var $tierBody = $('#pokehub-gp-tier-body');
        var $weeklyBody = $('#pokehub-gp-weekly-body');
        var $dailyBody = $('#pokehub-gp-daily-body');
        $form.find('.pokehub-go-pass-reward-editor').each(function () {
            goPassUpdateRewardBranches($(this));
        });

        $(document).on('change', '#pokehub-go-pass-form .pokehub-reward-type', function () {
            var $ed = $(this).closest('.pokehub-go-pass-reward-editor');
            goPassUpdateRewardBranches($ed);
            if ($(this).val() === 'bonus' && window.pokehubInitGoPassBonusRewardSelect2) {
                window.pokehubInitGoPassBonusRewardSelect2($ed);
            }
            var $tier = $ed.closest('tr.pokehub-gp-tier-row');
            if ($tier.length) {
                syncTierRowKind($tier);
                goPassSyncTierRanksFromChain();
            }
        });

        setTimeout(function () {
            initGoPassRewardSelects($form);
        }, 150);

        $form.find('tr.pokehub-gp-tier-row').each(function () {
            syncTierRowKind($(this));
        });

        if ($tierBody.length && typeof $tierBody.sortable === 'function') {
            $tierBody.sortable({
                handle: '.pokehub-gp-tier-drag-handle',
                axis: 'y',
                containment: 'parent',
                tolerance: 'pointer',
                update: function () {
                    goPassReindexTierRows();
                    goPassSyncTierRanksFromChain();
                }
            });
        }

        goPassReindexTierRows();
        goPassSyncTierRanksFromChain();

        $(document).on('change', '#pokehub-gp-tier-body tr.pokehub-gp-tier-row input[name$="[rank_to]"]', function () {
            if (goPassApplyingTierChain) {
                return;
            }
            if ($(this).attr('type') === 'hidden') {
                return;
            }
            goPassScheduleTierRankSync();
        });

        $(document).on('change', '#pokehub-gp-tier-body tr.pokehub-gp-tier-row input.pokehub-gp-tier-rank-start', function () {
            if (goPassApplyingTierChain) {
                return;
            }
            var $tr = $(this).closest('tr');
            if (goPassTierRowHasBonus($tr)) {
                return;
            }
            var $first = goPassFirstRewardRow();
            if (!$first.length || $tr[0] !== $first[0]) {
                return;
            }
            goPassScheduleTierRankSync();
        });

        $('#pokehub-gp-add-tier').on('click', function () {
            if (!$tierBody.length) {
                return;
            }
            var i = nextBracketIndex($tierBody);
            var $tr = $(tierRowHtml(i));
            $tierBody.append($tr);
            syncTierRowKind($tr);
            setTimeout(function () {
                goPassReindexTierRows();
                goPassSyncTierRanksFromChain();
                $tr.find('.pokehub-go-pass-reward-editor').each(function () {
                    goPassUpdateRewardBranches($(this));
                });
                initGoPassRewardSelects($tr);
            }, 50);
        });

        $(document).on('click', '.pokehub-gp-add-reward', function () {
            var prefix = $(this).data('prefix');
            if (!prefix) {
                return;
            }
            var $list = $(this).closest('.pokehub-go-pass-rewards-col').find('.pokehub-go-pass-rewards-list');
            var ri = nextRewardIndex($list);
            var $row = $(rewardRowHtml(prefix, ri));
            $list.append($row);
            goPassUpdateRewardBranches($row);
            setTimeout(function () {
                initGoPassRewardSelects($row);
            }, 50);
        });

        $(document).on('click', '.pokehub-gp-remove-reward', function () {
            var $ed = $(this).closest('.pokehub-go-pass-reward-editor');
            var $tier = $ed.closest('tr.pokehub-gp-tier-row');
            $ed.remove();
            if ($tier.length) {
                syncTierRowKind($tier);
                goPassSyncTierRanksFromChain();
            }
        });

        $('#pokehub-gp-add-weekly').on('click', function () {
            if (!$weeklyBody.length) {
                return;
            }
            var i = nextBracketIndex($weeklyBody);
            $weeklyBody.append(taskRowHtml('weekly', i));
        });

        $('#pokehub-gp-add-daily').on('click', function () {
            if (!$dailyBody.length) {
                return;
            }
            var i = nextBracketIndex($dailyBody);
            $dailyBody.append(taskRowHtml('daily', i));
        });

        $(document).on('click', '.pokehub-gp-remove-tier', function () {
            var $rows = $tierBody.find('tr.pokehub-gp-tier-row');
            if ($rows.length <= 1) {
                return;
            }
            $(this).closest('tr').remove();
            goPassReindexTierRows();
            goPassSyncTierRanksFromChain();
        });

        $(document).on('click', '.pokehub-gp-remove-weekly', function () {
            var $rows = $weeklyBody.find('tr.pokehub-gp-weekly-row');
            if ($rows.length <= 1) {
                return;
            }
            $(this).closest('tr').remove();
        });

        $(document).on('click', '.pokehub-gp-remove-daily', function () {
            var $rows = $dailyBody.find('tr.pokehub-gp-daily-row');
            if ($rows.length <= 1) {
                return;
            }
            $(this).closest('tr').remove();
        });

    });
})(jQuery);
