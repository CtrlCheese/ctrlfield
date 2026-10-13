import { describe, it, expect } from 'vitest';
import { slugify, keyProblem, groupKeyFor } from '../../assets/admin/src/posttypes.js';

describe('slugify', () => {
    it('makes a key from a name', () => {
        expect(slugify('Membro da Equipe')).toBe('membro_da_equipe');
        expect(slugify('Événements & Talks')).toBe('evenements_talks');
        expect(slugify('2024 Projects')).toBe('projects');
    });
    it('keeps to the maximum length without a trailing underscore', () => {
        expect(slugify('Very long post type name here', 20)).toBe('very_long_post_type');
        expect(slugify('x'.repeat(40), 32)).toHaveLength(32);
    });
});

describe('keyProblem', () => {
    const opts = { max: 20, reserved: ['post', 'year'], taken: ['event'] };
    it('accepts a valid key', () => expect(keyProblem('team_member', opts)).toBe(''));
    it('names the problem', () => {
        expect(keyProblem('', opts)).toBe('required');
        expect(keyProblem('Team', opts)).toBe('format');
        expect(keyProblem('a'.repeat(21), opts)).toBe('format');
        expect(keyProblem('year', opts)).toBe('reserved');
        expect(keyProblem('event', opts)).toBe('taken');
    });
});

describe('groupKeyFor', () => {
    it('makes a field group key without dashes', () => expect(groupKeyFor('team-member')).toBe('team_member_fields'));
});
