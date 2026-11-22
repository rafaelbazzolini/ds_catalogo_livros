#!/usr/bin/env python3
import argparse
import html
import json
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request
import uuid

argumentos = argparse.ArgumentParser(description='Verificação HTTP dos cadastros e relatórios.')
argumentos.add_argument('--url', default='http://127.0.0.1:8088')
base = argumentos.parse_args().url.rstrip('/')
cliente = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
prefixo = 'Teste '+uuid.uuid4().hex[:6]
criados = []


def requisicao(caminho, dados=None, esperado=200, caminho_final=None):
    corpo = urllib.parse.urlencode(dados, doseq=True).encode() if dados is not None else None
    try:
        resposta = cliente.open(base+caminho, corpo)
    except urllib.error.HTTPError as erro:
        resposta = erro
    conteudo = resposta.read()
    assert resposta.code == esperado, (caminho, resposta.code, conteudo[:400])
    if caminho_final:
        assert re.fullmatch(caminho_final, urllib.parse.urlparse(resposta.geturl()).path), resposta.geturl()
    return conteudo.decode() if 'pdf' not in resposta.headers.get('Content-Type', '') else conteudo


def token(conteudo):
    return html.unescape(re.search(r'name="_token" value="([^"]+)"', conteudo)[1])


def adicionar(tipo, dados):
    dados['_token'] = token(requisicao('/cadastro/'+tipo+'/novo'))
    salvo = requisicao('/cadastro/'+tipo+'/novo', dados, caminho_final='/cadastro/'+tipo+r'/\d+/editar')
    assert 'Cadastro salvo com sucesso.' in salvo
    listagem = requisicao('/cadastro/'+tipo+'?q='+urllib.parse.quote(prefixo))
    identificadores = re.findall('/cadastro/'+tipo+r'/(\d+)/editar', listagem)
    identificador = max(map(int, identificadores))
    criados.append((tipo, identificador))
    pagina = f'/cadastro/{tipo}/{identificador}/editar'
    dados['_token'] = token(requisicao(pagina))
    requisicao(pagina, dados, caminho_final=pagina)
    repetido = requisicao('/cadastro/'+tipo+'?q='+urllib.parse.quote(prefixo))
    assert re.findall('/cadastro/'+tipo+r'/(\d+)/editar', repetido) == identificadores
    return identificador


def excluir(tipo, identificador):
    listagem = requisicao('/cadastro/'+tipo+'?q='+urllib.parse.quote(prefixo))
    correspondencia = re.search(r'action="/cadastro/'+tipo+'/'+str(identificador)+r'/excluir".*?name="_token" value="([^"]+)"', listagem, re.S)
    return requisicao(f'/cadastro/{tipo}/{identificador}/excluir', {'_token': html.unescape(correspondencia[1])})


def cadastro_rapido(tipo, nome):
    campo = 'Nome' if tipo == 'autores' else 'Descricao'
    csrf = token(requisicao('/cadastro/'+tipo+'/novo'))
    requisicao('/cadastro/'+tipo+'/rapido', {campo: '', '_token': csrf}, 422)
    requisicao('/cadastro/'+tipo+'/rapido', {campo: nome, '_token': 'invalid'}, 403)
    resultado = json.loads(requisicao('/cadastro/'+tipo+'/rapido', {campo: nome, '_token': csrf}, 201))
    criados.append((tipo, resultado['id']))
    assert any(item['id'] == resultado['id'] and item['label'] == nome for item in resultado['items'])
    return resultado['id']


try:
    a = adicionar('autores', {'Nome': prefixo+' A'})
    b = cadastro_rapido('autores', prefixo+' B')
    s = adicionar('assuntos', {'Descricao': prefixo+' S'})
    t = cadastro_rapido('assuntos', prefixo+' T')
    opcoes = json.loads(requisicao('/cadastro/autores/opcoes?q='+urllib.parse.quote(prefixo)))
    assert len(opcoes['items']) == 2 and opcoes['hasMore'] is False
    dados = {'Status': 'disponivel', 'Titulo': prefixo+' Livro', 'Editora': 'Editora Teste', 'Edicao': '2', 'AnoPublicacao': '2026', 'Valor': '1.234,56', 'autores[]': [a, b], 'assuntos[]': [s, t]}
    livro = adicionar('livros', dados)
    assert 'vinculado a um livro' in excluir('autores', a)
    assert 'vinculado a um livro' in excluir('assuntos', s)
    for tipo, identificador, campo, valor in [('autores', a, 'Nome', prefixo+' Autor'), ('assuntos', s, 'Descricao', prefixo+' Tema')]:
        pagina = f'/cadastro/{tipo}/{identificador}/editar'
        salvo = requisicao(pagina, {campo: valor, '_token': token(requisicao(pagina))}, caminho_final=pagina)
        assert html.escape(valor, quote=True) in salvo
    pagina = f'/cadastro/livros/{livro}/editar'
    dados['Titulo'] = prefixo+' Revisado'
    dados['Status'] = 'indisponivel'
    dados['_token'] = token(requisicao(pagina))
    requisicao(pagina, dados, caminho_final=pagina)
    relatorio = requisicao('/relatorio')
    assert relatorio.count(prefixo+' Revisado') == 2
    assert 'Indisponível' in relatorio
    assert '<option value="indisponivel" selected>' in requisicao(pagina)
    assert prefixo+' Tema' in relatorio and prefixo+' T' in relatorio
    assert '1.234,56' in relatorio
    assert requisicao('/relatorio?format=pdf').startswith(b'%PDF-')
    invalido = dict(dados, Valor='-1,00')
    invalido['_token'] = token(requisicao(pagina))
    assert 'Confira os campos' in requisicao(pagina, invalido, 422)
    requisicao(pagina, dict(dados, _token='invalid'), 403)
    requisicao('/cadastro/livros/999999999/editar', esperado=404)
    print('OK: CRUDs, múltiplos vínculos, exclusão protegida, moeda, validação, CSRF, 404 e PDF.')
finally:
    for tipo, identificador in reversed(criados):
        excluir(tipo, identificador)
