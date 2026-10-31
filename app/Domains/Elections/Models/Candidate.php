<?php

namespace App\Domains\Elections\Models;

use App\Domains\Legislatures\Models\Legislator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'running_mate_of_id',
        'external_id',
        'ballot_number',
        'coverage_scope',
        'state',
        'office_code',
        'office_name',
        'civil_name',
        'ballot_name',
        'cpf',
        'party_acronym',
        'party_name',
        'education_level',
        'occupation',
        'race_color',
        'election_year',
        'raw_data',
        'photo_path',
        'proposal_document_path',
        'judgment_status',
    ];

    // O JSON bruto preserva dados do TSE que podem ser necessários em telas ou
    // sincronizações futuras; o ano é sempre tratado como número.
    protected $casts = [
        'raw_data' => 'array',
        'election_year' => 'integer',
    ];

    protected $appends = [
        'photo_url',
        'proposal_document_url',
    ];

    // Os caminhos são armazenados no banco; as URLs públicas são calculadas
    // na leitura e incluídas automaticamente no JSON por causa de $appends.
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path
            ? config('filesystems.disks.supabase.public_url').'/'.$this->photo_path
            : null;
    }

    public function getProposalDocumentUrlAttribute(): ?string
    {
        return $this->proposal_document_path
            ? config('filesystems.disks.supabase.public_url').'/'.$this->proposal_document_path
            : null;
    }

    /** Retorna o candidato titular ao qual este vice ou suplente está vinculado. */
    public function mainCandidate(): BelongsTo
    {
        // Relação recursiva: um vice/suplente aponta para o candidato titular
        // pelo campo running_mate_of_id na mesma tabela.
        return $this->belongsTo(Candidate::class, 'running_mate_of_id');
    }

    /** Alias legível da relação mainCandidate para o uso no restante do domínio. */
    public function runningMateOf(): BelongsTo
    {
        return $this->mainCandidate();
    }

    /** Retorna os vices e suplentes cujo campo running_mate_of_id aponta para este candidato. */
    public function runningMates(): HasMany
    {
        // Relação inversa da anterior: lista os candidatos cujo campo
        // running_mate_of_id referencia este titular.
        return $this->hasMany(Candidate::class, 'running_mate_of_id');
    }

    /** Restringe uma consulta aos titulares, excluindo vices e suplentes. */
    public function scopeMainCandidates($query)
    {
        return $query->whereNull('running_mate_of_id');
    }

    /** Restringe uma consulta a candidatos associados a um titular. */
    public function scopeRunningMates($query)
    {
        return $query->whereNotNull('running_mate_of_id');
    }

    /** Liga o candidato às legislaturas anteriores pelo CPF, já que os IDs pertencem a tabelas diferentes. */
    public function previousMandates()
    {
        // O vínculo com legislaturas anteriores usa CPF, pois os históricos
        // parlamentares e as candidaturas pertencem a tabelas distintas.
        return $this->hasMany(Legislator::class, 'cpf', 'cpf');
    }

    /** Retorna o histórico de candidatura em ordem decrescente de ano eleitoral. */
    public function candidacyHistory(): HasMany
    {
        return $this->hasMany(CandidacyHistory::class)->orderByDesc('election_year');
    }

    /** Retorna as despesas de campanha declaradas para esta candidatura. */
    public function expenses(): HasMany
    {
        return $this->hasMany(CandidateExpense::class);
    }

    /** Filtra candidaturas indeferidas pelo TSE. */
    public function scopeRejected($query)
    {
        // O sincronizador grava a situação normalizada em caixa alta.
        return $query->where('judgment_status', 'INDEFERIDO');
    }

    /** Filtra candidaturas deferidas pelo TSE. */
    public function scopeApproved($query)
    {
        return $query->where('judgment_status', 'DEFERIDO');
    }
}
