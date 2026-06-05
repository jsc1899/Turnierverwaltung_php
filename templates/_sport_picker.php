<div class="d-flex gap-1 align-items-center sport-picker">
  <?php $cur = $sport_current ?? ''; ?>
  <input type="hidden" name="sport" class="sport-val" value="<?= e($cur) ?>">
  <button type="button" data-val="" class="btn btn-sm sport-opt <?= $cur === '' ? 'btn-primary' : 'btn-outline-secondary' ?>"
          onclick="setSport(this)" title="Keine">—</button>
  <button type="button" data-val="tischtennis" class="btn btn-sm sport-opt <?= $cur === 'tischtennis' ? 'btn-primary' : 'btn-outline-secondary' ?>"
          onclick="setSport(this)" title="Tischtennis"
          style="font-size:1.4rem;padding:.1rem .35rem;line-height:1">🏓</button>
  <button type="button" data-val="tennis" class="btn btn-sm sport-opt <?= $cur === 'tennis' ? 'btn-primary' : 'btn-outline-secondary' ?>"
          onclick="setSport(this)" title="Tennis"
          style="font-size:1.4rem;padding:.1rem .35rem;line-height:1">🎾</button>
  <button type="button" data-val="fussball" class="btn btn-sm sport-opt <?= $cur === 'fussball' ? 'btn-primary' : 'btn-outline-secondary' ?>"
          onclick="setSport(this)" title="Fußball"
          style="font-size:1.4rem;padding:.1rem .35rem;line-height:1">⚽</button>
  <button type="button" data-val="cornhole" class="btn btn-sm sport-opt <?= $cur === 'cornhole' ? 'btn-primary' : 'btn-outline-secondary' ?>"
          onclick="setSport(this)" title="Cornhole" style="padding:.15rem .35rem">
    <img src="<?= url('static/cornhole_icon.svg') ?>" height="22" alt="Cornhole">
  </button>
</div>
