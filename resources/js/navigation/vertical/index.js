import inventario from './inventario'
import producto from './producto'
import usuario from './usuario'
import feria from './feria'
import Contrato from './contrato'

export default [ ...feria,...Contrato, ...inventario, ...producto, ...usuario]
