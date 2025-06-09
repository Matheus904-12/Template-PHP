<?php
include 'controller/Clientes/clientesController.php'; // Verifique se o caminho está certo

$clientes = getClientes();
?>
<link href="/dist/styles.css" rel="stylesheet">

<h2 class="text-2xl font-bold mb-4">Lista de Clientes</h2>
<table class="table table-dark table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Email</th>
            <th>Telefone</th>
            <th>Endereço</th>
            <th>CEP</th>
            <th>Número da Casa</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($clientes as $cliente): ?>
            <tr>
                <td><?= $cliente['id'] ?></td>
                <td><?= $cliente['name'] ?></td>
                <td><?= $cliente['email'] ?></td> 
                <td><?= $cliente['telefone'] ?></td>
                <td><?= $cliente['endereco'] ?></td>
                <td><?= $cliente['cep'] ?></td>
                <td><?= $cliente['numero_casa'] ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>