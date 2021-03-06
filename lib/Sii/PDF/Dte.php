<?php

/**
 * LibreDTE
 * Copyright (C) SASCO SpA (https://sasco.cl)
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General GNU
 * publicada por la Fundación para el Software Libre, ya sea la versión
 * 3 de la Licencia, o (a su elección) cualquier versión posterior de la
 * misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General GNU para obtener
 * una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General GNU
 * junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/gpl.html>.
 */

namespace sasco\LibreDTE\Sii\PDF;

/**
 * Clase para generar el PDF de un documento tributario electrónico (DTE)
 * chileno.
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
 * @version 2015-09-16
 */
class Dte extends \sasco\LibreDTE\PDF
{

    private $logo; ///< Ubicación del logo del emisor que se incluirá en el pdf
    private $resolucion; ///< Arreglo con los datos de la resolución (índices: NroResol y FchResol)
    private $cedible = false; ///< Por defecto DTEs no son cedibles

    private $tipos = [
        33 => 'FACTURA ELECTRÓNICA',
        34 => 'FACTURA NO AFECTA O EXENTA ELECTRÓNICA',
        43 => 'LIQUIDACIÓN FACTURA ELECTRÓNICA',
        46 => 'FACTURA DE COMPRA ELECTRÓNICA',
        52 => 'GUÍA DE DESPACHO ELECTRÓNICA',
        56 => 'NOTA DE DÉBITO ELECTRÓNICA',
        61 => 'NOTA DE CRÉDITO ELECTRÓNICA',
        110 => 'FACTURA DE EXPORTACIÓN ELECTRÓNICA',
        111 => 'NOTA DE DÉBITO DE EXPORTACIÓN ELECTRÓNICA',
        112 => 'NOTA DE CRÉDITO DE EXPORTACIÓN ELECTRÓNICA',
    ]; ///< Glosas para los tipos de documentos

    private $formas_pago = [
        1 => 'Contado',
        2 => 'Crédito',
        3 => 'Sin costo (entrega gratuita)',
    ]; ///< Glosas de las formas de pago

    private $detalle_cols = [
        'CdgItem' => ['title'=>'Código', 'align'=>'left', 'width'=>20],
        'NmbItem' => ['title'=>'Item', 'align'=>'left', 'width'=>0],
        'QtyItem' => ['title'=>'Cant.', 'align'=>'right', 'width'=>15],
        'UnmdItem' => ['title'=>'Unidad', 'align'=>'left', 'width'=>22],
        'PrcItem' => ['title'=>'P. unitario', 'align'=>'right', 'width'=>22],
        'DescuentoMonto' => ['title'=>'Descuento', 'align'=>'right', 'width'=>22],
        'RecargoMonto' => ['title'=>'Recargo', 'align'=>'right', 'width'=>22],
        'MontoItem' => ['title'=>'Total item', 'align'=>'right', 'width'=>22],
    ]; ///< Nombres de columnas detalle, alineación y ancho

    private $traslados = [
        1 => 'Operación constituye venta',
        2 => 'Ventas por efectuar',
        3 => 'Consignaciones',
        4 => 'Entrega gratuita',
        5 => 'Traslados internos',
        6 => 'Otros traslados no venta',
        7 => 'Guía de devolución',
        8 => 'Traslado para exportación (no venta)',
        9 => 'Venta para exportación',
    ]; ///< Tipos de traslado para guías de despacho

    private $sinAcuseRecibo = [56, 61, 111, 112]; ///< Notas de crédito y notas de débito no tienen acuse de recibo

    /**
     * Constructor de la clase
     * @param papelContinuo =true indica que el PDF se generará en formato papel continuo (si se pasa un número será el ancho del PDF)
     * @todo Implementar versión del PDF en papel contínuo
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-11-16
     */
    public function __construct($papelContinuo = false)
    {
        parent::__construct();
        $this->SetTitle('Documento Tributario Electrónico (DTE) de Chile');
    }

    /**
     * Método que asigna la ubicación del logo de la empresa
     * @param logo URI del logo (puede ser local o en una URL)
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    public function setLogo($logo)
    {
        $this->logo = $logo;
    }

    /**
     * Método que asigna los datos de la resolución del SII que autoriza al
     * emisor a emitir DTEs
     * @param resolucion Arreglo con índices NroResol y FchResol
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    public function setResolucion(array $resolucion)
    {
        $this->resolucion = $resolucion;
    }

    /**
     * Método que indica si el documento será o no cedible
     * @param cedible =true se incorporará leyenda de destino
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-09
     */
    public function setCedible($cedible = true)
    {
        $this->cedible = $cedible;
    }

    /**
     * Método que agrega una página con el documento tributario
     * @param dte Arreglo con los datos del XML (tag Documento)
     * @param timbre String XML con el tag TED del DTE
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-10-04
     */
    public function agregar(array $dte, $timbre)
    {
        // agregar página para la factura
        $this->AddPage();
        // agregar cabecera del documento
        $this->agregarEmisor($dte['Encabezado']['Emisor']);
        $this->agregarFolio(
            $dte['Encabezado']['Emisor']['RUTEmisor'],
            $dte['Encabezado']['IdDoc']['TipoDTE'],
            $dte['Encabezado']['IdDoc']['Folio'],
            $dte['Encabezado']['Emisor']['CmnaOrigen']
        );
        // datos del documento
        $this->setY(50);
        $this->agregarFechaEmision($dte['Encabezado']['IdDoc']['FchEmis']);
        if (!empty($dte['Encabezado']['IdDoc']['FmaPago']))
            $this->agregarCondicionVenta($dte['Encabezado']['IdDoc']['FmaPago']);
        $this->agregarReceptor($dte['Encabezado']['Receptor']);
        $this->agregarTraslado(
            !empty($dte['Encabezado']['IdDoc']['IndTraslado']) ? $dte['Encabezado']['IdDoc']['IndTraslado'] : null,
            !empty($dte['Encabezado']['Transporte']) ? $dte['Encabezado']['Transporte'] : null
        );
        if (!empty($dte['Referencia']))
            $this->agregarReferencia($dte['Referencia']);
        $this->agregarDetalle($dte['Detalle']);
        if (!empty($dte['DscRcgGlobal']))
            $this->agregarDescuentosRecargos($dte['DscRcgGlobal']);
        $this->agregarTotales($dte['Encabezado']['Totales']);
        // agregar timbre
        $this->agregarTimbre($timbre);
        // agregar acuse de recibo y leyenda de destino sólo si no es nota de
        // crédito ni nota de débito
        if (!in_array($dte['Encabezado']['IdDoc']['TipoDTE'], $this->sinAcuseRecibo)) {
            $this->agregarAcuseRecibo();
            if ($this->cedible)
                $this->agregarLeyendaDestino($dte['Encabezado']['IdDoc']['TipoDTE']);
        }
    }

    /**
     * Método que agrega los datos de la empresa
     * Orden de los datos:
     *  - Razón social del emisor
     *  - Giro del emisor (sin abreviar)
     *  - Dirección casa central del emisor
     *  - Dirección sucursales
     * @param emisor Arreglo con los datos del emisor (tag Emisor del XML)
     * @param x Posición horizontal de inicio en el PDF
     * @param y Posición vertical de inicio en el PDF
     * @param w Ancho de la información del emisor
     * @param w_img Ancho máximo de la imagen
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-10-04
     */
    private function agregarEmisor(array $emisor, $x = 10, $y = 10, $w = 75, $w_img = 30)
    {
        // logo máximo 1/5 del tamaño del documento
        if (isset($this->logo)) {
            $this->Image($this->logo, $x, $y, $w_img, 0, 'PNG', (isset($emisor['url'])?$emisor['url']:''), 'T');
            $x = $this->x+3;
        } else {
            $this->y = $y-2;
            $w += 40;
        }
        // agregar datos del emisor
        $this->setFont('', 'B', 20);
        $this->SetTextColorArray([32, 92, 144]);
        $this->MultiTexto($emisor['RznSoc'], $x, $this->y+2, 'L', $w);
        $this->setFont('', 'B', 9);
        $this->SetTextColorArray([0,0,0]);
        $this->MultiTexto($emisor['GiroEmis'], $x, $this->y, 'L', $w);
        $this->MultiTexto($emisor['DirOrigen'].', '.$emisor['CmnaOrigen'], $x, $this->y, 'L', $w);
        $contacto = [];
        if (!empty($emisor['Telefono'])) {
            if (!is_array($emisor['Telefono']))
                $emisor['Telefono'] = [$emisor['Telefono']];
            foreach ($emisor['Telefono'] as $t)
                $contacto[] = $t;
        }
        if (!empty($emisor['CorreoEmisor']))
            $contacto[] = $emisor['CorreoEmisor'];
        if ($contacto)
            $this->MultiTexto(implode(' / ', $contacto), $x, $this->y, 'L', $w);
    }

    /**
     * Método que agrega el recuadro con el folio
     * Recuadro:
     *  - Tamaño mínimo 1.5x5.5 cms
     *  - En lado derecho (negro o rojo)
     *  - Enmarcado por una línea de entre 0.5 y 1 mm de espesor
     *  - Tamaño máximo 4x8 cms
     *  - Letras tamaño 10 o superior en mayúsculas y negritas
     *  - Datos del recuadro: RUT emisor, nombre de documento en 2 líneas,
     *    folio.
     *  - Bajo el recuadro indicar la Dirección regional o Unidad del SII a la
     *    que pertenece el emisor
     * @param rut RUT del emisor
     * @param tipo Código o glosa del tipo de documento
     * @param sucursal_sii Código o glosa de la sucursal del SII del Emisor
     * @param x Posición horizontal de inicio en el PDF
     * @param y Posición vertical de inicio en el PDF
     * @param w Ancho de la información del emisor
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-10-02
     */
    private function agregarFolio($rut, $tipo, $folio, $sucursal_sii = null, $x = 130, $y = 10, $w = 70)
    {
        $color = $tipo==52 ? [0,172,140] : [255,0,0];
        $this->SetTextColorArray($color);
        // colocar rut emisor, glosa documento y folio
        list($rut, $dv) = explode('-', $rut);
        $this->setFont ('', 'B', 15);
        $this->MultiTexto('R.U.T.: '.$this->num($rut).'-'.$dv, $x, $y+4, 'C', $w);
        $this->setFont('', 'B', 12);
        $this->MultiTexto($this->getTipo($tipo), $x, null, 'C', $w);
        $this->setFont('', 'B', 15);
        $this->MultiTexto('N° '.$folio, $x, null, 'C', $w);
        // dibujar rectángulo rojo
        $this->Rect($x, $y, $w, round($this->getY()-$y+3), 'D', ['all' => ['width' => 0.5, 'color' => $color]]);
        // colocar unidad del SII
        $this->setFont('', 'B', 10);
        $this->Texto('S.I.I. - '.$this->getSucursalSII($sucursal_sii), $x, $this->getY()+4, 'C', $w);
        $this->SetTextColorArray([0,0,0]);
    }

    /**
     * Método que entrega la glosa del tipo de documento
     * @param tipo Código del tipo de documento
     * @return Glosa del tipo de documento
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    private function getTipo($tipo)
    {
        if (!is_numeric($tipo))
            return $tipo;
        return isset($this->tipos[$tipo]) ? strtoupper($this->tipos[$tipo]) : 'DTE '.$tipo;
    }

    /**
     * Método que entrega la sucursal del SII asociada al emisor
     * @param codigo de la sucursal del SII
     * @return Sucursal del SII
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-09
     */
    private function getSucursalSII($codigo)
    {
        if (!is_numeric($codigo)) {
            $sucursal = strtoupper($codigo);
            return $sucursal=='SANTIAGO' ? 'SANTIAGO CENTRO' : $sucursal;
        }
        return 'SUC '.$codigo;
    }

    /**
     * Método que agrega la fecha de emisión de la factura
     * @param date Fecha de emisión de la boleta en formato AAAA-MM-DD
     * @param x Posición horizontal de inicio en el PDF
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-09
     */
    private function agregarFechaEmision($date, $x = 10)
    {
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $unixtime = strtotime($date);
        $fecha = date('\D\I\A j \d\e \M\E\S \d\e\l Y', $unixtime);
        $dia = $dias[date('w', $unixtime)];
        $mes = $meses[date('n', $unixtime)-1];
        $this->Texto('Emisión', $x);
        $this->Texto(':', $x+22);
        $this->MultiTexto(str_replace(array('DIA', 'MES'), array($dia, $mes), $fecha), $x+26);
    }

    /**
     * Método que agrega la condición de venta del documento
     * @param condicion_venta Código de la condición de venta (tag FmaPago XML)
     * @param x Posición horizontal de inicio en el PDF
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-09
     */
    private function agregarCondicionVenta($condicion_venta, $x = 10)
    {
        $this->Texto('Venta', $x);
        $this->Texto(':', $x+22);
        $this->MultiTexto($this->formas_pago[$condicion_venta], $x+26);
    }

    /**
     * Método que agrega los datos del receptor
     * @param receptor Arreglo con los datos del receptor (tag Receptor del XML)
     * @param x Posición horizontal de inicio en el PDF
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-12
     */
    private function agregarReceptor(array $receptor, $x = 10)
    {
        list($rut, $dv) = explode('-', $receptor['RUTRecep']);
        $this->Texto('Señor(es)', $x);
        $this->Texto(':', $x+22);
        $this->MultiTexto($receptor['RznSocRecep'], $x+26);
        $this->Texto('R.U.T.', $x);
        $this->Texto(':', $x+22);
        $this->MultiTexto($this->num($rut).'-'.$dv, $x+26);
        $this->Texto('Giro', $x);
        $this->Texto(':', $x+22);
        $this->MultiTexto($receptor['GiroRecep'], $x+26);
        $this->Texto('Dirección', $x);
        $this->Texto(':', $x+22);
        $this->MultiTexto($receptor['DirRecep'].', '.$receptor['CmnaRecep'], $x+26);
        $contacto = [];
        if (!empty($receptor['Contacto']))
            $contacto[] = $receptor['Contacto'];
        if (!empty($receptor['CorreoRecep']))
            $contacto[] = $receptor['CorreoRecep'];
        if (!empty($contacto)) {
            $this->Texto('Contacto', $x);
            $this->Texto(':', $x+22);
            $this->MultiTexto(implode(' / ', $contacto), $x+26);
        }
    }

    /**
     * Método que agrega los datos del traslado
     * @param IndTraslado
     * @param Transporte
     * @param x Posición horizontal de inicio en el PDF
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-10-04
     */
    private function agregarTraslado($IndTraslado, array $Transporte = null, $x = 10)
    {
        // agregar tipo de traslado
        if ($IndTraslado) {
            $this->Texto('Traslado', $x);
            $this->Texto(':', $x+22);
            $this->MultiTexto($this->traslados[$IndTraslado], $x+26);
        }
        // agregar información de transporte
        if ($Transporte) {
            $transporte = '';
            if (!empty($Transporte['DirDest']) and !empty($Transporte['CmnaDest'])) {
                $transporte .= 'a '.$Transporte['DirDest'].', '.$Transporte['CmnaDest'].' ';
            }
            if (!empty($Transporte['RUTTrans']))
                $transporte .= ' por '.$Transporte['RUTTrans'];
            if (!empty($Transporte['Patente']))
                $transporte .= ' en vehículo '.$Transporte['Patente'];
            if (is_array($Transporte['Chofer'])) {
                if (!empty($Transporte['Chofer']['NombreChofer']))
                    $transporte .= ' con chofér '.$Transporte['Chofer']['NombreChofer'];
                if (!empty($Transporte['Chofer']['RUTChofer']))
                    $transporte .= ' ('.$Transporte['Chofer']['RUTChofer'].')';
            }
            if ($transporte) {
                $this->Texto('Transporte', $x);
                $this->Texto(':', $x+22);
                $this->MultiTexto(ucfirst(trim($transporte)), $x+26);
            }
        }
    }

    /**
     * Método que agrega las referencias del documento
     * @param referencias Arreglo con las referencias del documento (tag Referencia del XML)
     * @param x Posición horizontal de inicio en el PDF
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    private function agregarReferencia($referencias, $x = 10)
    {
        if (!isset($referencias[0]))
            $referencias = [$referencias];
        foreach($referencias as $r) {
            $texto = $r['NroLinRef'].' - '.$this->getTipo($r['TpoDocRef']).' N° '.$r['FolioRef'].' del '.$r['FchRef'].': '.$r['RazonRef'];
            $this->Texto('Referenc.', $x);
            $this->Texto(':', $x+22);
            $this->MultiTexto($texto, $x+26);
        }
    }

    /**
     * Método que agrega el detalle del documento
     * @param detalle Arreglo con el detalle del documento (tag Detalle del XML)
     * @param x Posición horizontal de inicio en el PDF
     * @param y Posición vertical de inicio en el PDF
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-22
     */
    private function agregarDetalle($detalle, $x = 10)
    {
        if (!isset($detalle[0]))
            $detalle = [$detalle];
        // titulos
        $titulos = [];
        $titulos_keys = array_keys($this->detalle_cols);
        foreach ($this->detalle_cols as $key => $info) {
            $titulos[$key] = $info['title'];
        }
        // normalizar cada detalle
        foreach ($detalle as &$item) {
            // quitar columnas
            foreach ($item as $col => $valor) {
                if ($col=='DscItem') {
                    $item['NmbItem'] .= '<br/><span style="font-size:0.7em">'.$item['DscItem'].'</span>';
                }
                if (!in_array($col, $titulos_keys))
                    unset($item[$col]);
            }
            // agregar todas las columnas que se podrían imprimir en la tabla
            $item_default = [];
            foreach ($this->detalle_cols as $key => $info)
                $item_default[$key] = false;
            $item = array_merge($item_default, $item);
            // si hay código de item se extrae su valor
            if ($item['CdgItem'])
                $item['CdgItem'] = $item['CdgItem']['VlrCodigo'];
            // dar formato a números
            foreach (['QtyItem', 'PrcItem', 'DescuentoMonto', 'RecargoMonto', 'MontoItem'] as $col) {
                if ($item[$col])
                    $item[$col] = $this->num($item[$col]);
            }
        }
        // opciones
        $options = ['align'=>[]];
        $i = 0;
        foreach ($this->detalle_cols as $info) {
            if (isset($info['width']))
                $options['width'][$i] = $info['width'];
            $options['align'][$i] = $info['align'];
            $i++;
        }
        // agregar tabla de detalle
        $this->Ln();
        $this->SetX($x);
        $this->addTableWithoutEmptyCols($titulos, $detalle, $options);
    }

    /**
     * Método que agrega los descuentos y/o recargos globales del documento
     * @param descuentosRecargos Arreglo con los descuentos y/o recargos del documento (tag DscRcgGlobal del XML)
     * @param x Posición horizontal de inicio en el PDF
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-09
     */
    private function agregarDescuentosRecargos(array $descuentosRecargos, $x = 10)
    {
        if (!isset($descuentosRecargos[0]))
            $descuentosRecargos = [$descuentosRecargos];
        foreach($descuentosRecargos as $dr) {
            $tipo = $dr['TpoMov']=='D' ? 'Descuento' : 'Recargo';
            $valor = $dr['TpoValor']=='%' ? $dr['ValorDR'].'%' : '$'.$this->num($dr['ValorDR']).'.-';
            $this->Texto($tipo.' global de '.$valor, $x);
        }
    }

    /**
     * Método que agrega los totales del documento
     * @param totales Arreglo con los totales (tag Totales del XML)
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-09
     */
    private function agregarTotales(array $totales, $y = 190)
    {
        // normalizar totales
        $totales = array_merge([
            'MntNeto' => false,
            'MntExe' => false,
            'TasaIVA' => false,
            'IVA' => false,
            'MntTotal' => false,
        ], $totales);
        // glosas
        $glosas = [
            'MntNeto' => 'Neto $',
            'MntExe' => 'Exento $',
            'IVA' => 'I.V.A. ('.$totales['TasaIVA'].'%)',
            'MntTotal' => 'Total $',
        ];
        // agregar cada uno de los totales
        $this->setY($y);
        foreach ($totales as $key => $total) {
            if ($total!==false and isset($glosas[$key])) {
                $x = 175;
                $this->Texto($glosas[$key].' :', $x, null, 'R', 1);
                $this->Texto($this->num($total), $x+25, null, 'R', 1);
                $this->Ln();
            }
        }
    }

    /**
     * Método que agrega el timbre de la factura
     *  - Se imprime en el tamaño mínimo: 2x5 cms
     *  - En el lado de abajo con margen izquierdo mínimo de 2 cms
     * @param timbre String con los datos del timbre
     * @param x Posición horizontal de inicio en el PDF
     * @param y Posición vertical de inicio en el PDF
     * @param w Ancho del timbre
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    private function agregarTimbre($timbre, $x = 20, $y = 190, $w = 70)
    {
        $style = [
            'border' => false,
            'vpadding' => 0,
            'hpadding' => 0,
            'fgcolor' => [0,0,0],
            'bgcolor' => false, // [255,255,255]
            'module_width' => 1, // width of a single module in points
            'module_height' => 1 // height of a single module in points
        ];
        $this->write2DBarcode($timbre, 'PDF417', $x, $y, $w, 0, $style, 'B');
        $this->setFont('', 'B', 8);
        $this->Texto('Timbre Electrónico SII', $x, $this->y, 'C', $w);
        $this->Texto('Resolución '.$this->resolucion['NroResol'].' de '.explode('-', $this->resolucion['FchResol'])[0], $x, $this->y+4, 'C', $w);
        $this->Texto('Verifique documento: www.sii.cl', $x, $this->y+4, 'C', $w, 'http://www.sii.cl');
    }

    /**
     * Método que agrega el acuse de rebido
     * @param x Posición horizontal de inicio en el PDF
     * @param y Posición vertical de inicio en el PDF
     * @param w Ancho del acuse de recibo
     * @param h Alto del acuse de recibo
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    private function agregarAcuseRecibo($x = 93, $y = 190, $w = 50, $h = 40)
    {
        $this->SetTextColorArray([0,0,0]);
        $this->Rect($x, $y, $w, $h, 'D', ['all' => ['width' => 0.1, 'color' => [0, 0, 0]]]);
        $this->setFont('', 'B', 10);
        $this->Texto('Acuse de recibo', $x, $y+1, 'C', $w);
        $this->setFont('', 'B', 8);
        $this->Texto('Nombre', $x+2, $this->y+8);
        $this->Texto('________________', $x+18);
        $this->Texto('R.U.T.', $x+2, $this->y+6);
        $this->Texto('________________', $x+18);
        $this->Texto('Fecha', $x+2, $this->y+6);
        $this->Texto('________________', $x+18);
        $this->Texto('Recinto', $x+2, $this->y+6);
        $this->Texto('________________', $x+18);
        $this->Texto('Firma', $x+2, $this->y+8);
        $this->Texto('________________', $x+18);
        $this->setFont('', 'B', 7);
        $this->MultiTexto('El acuse de recibo que se declara en este acto, de acuerdo a lo dispuesto en la letra b) del Art. 4°, y la letra c) del Art. 5° de la Ley 19.983, acredita que la entrega de mercaderías o servicio (s) prestado (s) ha (n) sido recibido (s).'."\n", $x, $this->y+6, 'J', $w);
    }

    /**
     * Método que agrega la leyenda de destino
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-10-02
     */
    private function agregarLeyendaDestino($tipo, $y = 245)
    {
        $this->setFont('', 'B', 10);
        $this->Texto('CEDIBLE'.($tipo==52?' CON SU FACTURA':''), null, $y, 'R');
    }

    /**
     * Método que formatea un número con separador de miles y decimales (si
     * corresponden)
     * @param n Número que se desea formatear
     * @param d Cantidad de decimales
     * @return Número formateado
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    private function num($n, $d=0)
    {
        return number_format((float)$n, $d, ',', '.');
    }

}
