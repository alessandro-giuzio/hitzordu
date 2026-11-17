import { describe, expect, it } from 'vitest'
import { catalogueKeys, createTranslator, languageOf } from '../src/lib/i18n'

describe('languageOf', () => {
  it('reads the language from WordPress and browser locales', () => {
    expect(languageOf('es-ES')).toBe('es')
    expect(languageOf('it_IT')).toBe('it')
    expect(languageOf('eu')).toBe('eu')
  })

  it('falls back to English for languages without a catalogue', () => {
    expect(languageOf('fr-FR')).toBe('en')
  })
})

describe('createTranslator', () => {
  it('fills in placeholders', () => {
    expect(createTranslator('en')('emailOnItsWay', { email: 'ana@example.com' })).toBe(
      'A confirmation email is on its way to ana@example.com.',
    )
  })

  it('chooses the plural form for the count', () => {
    const t = createTranslator('es-ES')
    expect(t('dayTimes', { date: 'lunes', count: 1 })).toBe('lunes, 1 hora disponible')
    expect(t('dayTimes', { date: 'lunes', count: 4 })).toBe('lunes, 4 horas disponibles')
  })
})

describe('catalogues', () => {
  it('define every message in every language', () => {
    const keys = catalogueKeys()
    for (const lang of Object.keys(keys)) {
      expect(keys[lang], lang).toEqual(keys.en)
    }
  })
})
